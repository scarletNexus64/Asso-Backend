<?php

namespace Tests\Feature;

use App\Models\DiaspoOffer;
use App\Models\DiaspoVerificationEvent;
use App\Models\ServiceConfiguration;
use App\Models\User;
use App\Services\DiaspoVerificationService;
use App\Services\FirebaseMessagingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * P7 Diaspo : publication d'une offre avant la validation de l'identité.
 *
 * - publiée tout de suite avec la mention « Profil non vérifié », non réservable ;
 * - mention retirée à la validation de l'identité ;
 * - retrait automatique à l'échéance de régularisation fixée par ASSO ;
 * - chaque étape tracée dans diaspo_verification_events et visible dans l'admin.
 */
class DiaspoUnverifiedPublicationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->mock(FirebaseMessagingService::class, function ($m) {
            $m->shouldReceive('sendToUser')->andReturn([]);
            $m->shouldReceive('sendToTopic')->andReturn([]);
        });
    }

    private function admin(): User
    {
        $u = User::factory()->create();
        $u->forceFill(['role' => 'admin'])->saveQuietly();

        return $u;
    }

    private function offerPayload(): array
    {
        return [
            'departure_country' => 'FR', 'departure_city' => 'Paris',
            'departure_datetime' => now()->addDays(20)->toDateTimeString(),
            'arrival_country' => 'CM', 'arrival_city' => 'Douala',
            'arrival_datetime' => now()->addDays(21)->toDateTimeString(),
            'price_per_kg' => 10, 'available_kg' => 20, 'currency' => 'EUR',
        ];
    }

    private function publishAs(User $traveller): DiaspoOffer
    {
        Sanctum::actingAs($traveller);
        $this->postJson('/api/v1/diaspo/offers', $this->offerPayload())->assertCreated();

        return DiaspoOffer::where('user_id', $traveller->id)->latest('id')->firstOrFail();
    }

    private function withDocuments(User $user): User
    {
        // Les pièces sont des Document : on simule leur dépôt via le statut + une référence.
        $doc = \App\Models\Document::create([
            'title' => 'Document DIASPO (Recto) - CNI', 'file_name' => 'recto.jpg',
            'file_path' => 'documents/diaspo_verification/recto.jpg', 'file_type' => 'jpg',
            'file_size' => 10, 'mime_type' => 'image/jpeg', 'uploaded_by' => $user->id,
            'visibility' => 'restricted', 'allowed_users' => [$user->id],
        ]);
        $user->forceFill(['diaspo_id_document_id' => $doc->id, 'diaspo_verification_status' => 'pending'])->save();

        return $user;
    }

    public function test_unverified_traveller_offer_is_published_with_unverified_mention(): void
    {
        DiaspoVerificationService::setGraceDays(10);
        $traveller = User::factory()->create();

        Sanctum::actingAs($traveller);
        $response = $this->postJson('/api/v1/diaspo/offers', $this->offerPayload())->assertCreated();
        $this->assertStringContainsString('Profil non vérifié', $response->json('message'));

        $offer = DiaspoOffer::where('user_id', $traveller->id)->firstOrFail();
        $this->assertSame('approved', $offer->status);
        $this->assertSame('pending', $offer->verification_status);
        $this->assertEqualsWithDelta(now()->addDays(10)->timestamp, $offer->verification_deadline_at->timestamp, 5);
        $this->assertDatabaseHas('diaspo_verification_events', [
            'diaspo_offer_id' => $offer->id,
            'event' => DiaspoVerificationEvent::OFFER_PUBLISHED_UNVERIFIED,
        ]);

        // Visible dans le catalogue public, signalée non vérifiée et non réservable.
        Sanctum::actingAs(User::factory()->create());
        $list = $this->getJson('/api/v1/diaspo/offers')->assertOk()->json('data.data');
        $this->assertCount(1, $list);
        $this->assertFalse($list[0]['profile_verified']);
        $this->assertTrue($list[0]['is_published']);
        $this->assertFalse($list[0]['is_available']);

        Sanctum::actingAs($traveller);
        $status = $this->getJson('/api/v1/diaspo/verification-status')->assertOk()->json('data');
        $this->assertSame(10, $status['grace_days']);
        $this->assertNotNull($status['next_deadline']);
    }

    public function test_verified_traveller_offer_is_published_without_mention(): void
    {
        $traveller = User::factory()->create();
        $traveller->forceFill(['diaspo_verification_status' => 'verified'])->save();

        $offer = $this->publishAs($traveller);

        $this->assertSame('verified', $offer->verification_status);
        $this->assertNull($offer->verification_deadline_at);
        $this->assertTrue($offer->profile_verified);
    }

    public function test_offer_of_unverified_profile_cannot_be_booked(): void
    {
        ServiceConfiguration::updateOrCreate(['service_name' => 'kpay'], [
            'service_type' => 'payment', 'is_active' => true,
            'configuration' => ['api_key' => 'k', 'secret_key' => 's', 'base_url' => 'https://kpay.test'],
        ]);
        $offer = $this->publishAs(User::factory()->create());

        Sanctum::actingAs(User::factory()->create());
        $this->postJson("/api/v1/diaspo/offers/{$offer->id}/book", [
            'kg_booked' => 2, 'payment_method' => 'kpay', 'provider' => 'orange', 'phone_number' => '690000000',
        ])->assertStatus(422)->assertJsonFragment(['success' => false]);

        $this->assertStringContainsString('vérifier son identité', $this->postJson("/api/v1/diaspo/offers/{$offer->id}/book", [
            'kg_booked' => 2, 'payment_method' => 'kpay', 'provider' => 'orange', 'phone_number' => '690000000',
        ])->json('message'));
        $this->assertSame(0, $offer->bookings()->count());
    }

    public function test_identity_approval_removes_the_mention_and_is_traced(): void
    {
        $traveller = User::factory()->create();
        $offer = $this->publishAs($traveller);
        $this->withDocuments($traveller);
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('admin.diaspo.verifications.approve', $traveller->id))
            ->assertRedirect(route('admin.diaspo.verifications.index'));

        $offer->refresh();
        $this->assertSame('verified', $offer->verification_status);
        $this->assertSame('approved', $offer->status);
        $this->assertTrue($offer->is_available);
        $this->assertDatabaseHas('diaspo_verification_events', [
            'user_id' => $traveller->id, 'event' => DiaspoVerificationEvent::IDENTITY_VERIFIED, 'actor_id' => $admin->id,
        ]);
        $this->assertDatabaseHas('diaspo_verification_events', [
            'diaspo_offer_id' => $offer->id, 'event' => DiaspoVerificationEvent::OFFER_VERIFIED,
        ]);
    }

    public function test_rejected_documents_keep_offer_online_until_deadline(): void
    {
        $traveller = User::factory()->create();
        $offer = $this->publishAs($traveller);
        $this->withDocuments($traveller);

        $this->actingAs($this->admin())
            ->post(route('admin.diaspo.verifications.reject', $traveller->id), ['reason' => 'Photo illisible'])
            ->assertRedirect();

        $offer->refresh();
        $this->assertSame('approved', $offer->status);
        $this->assertSame('pending', $offer->verification_status);
        $this->assertDatabaseHas('diaspo_verification_events', [
            'user_id' => $traveller->id, 'event' => DiaspoVerificationEvent::IDENTITY_REJECTED, 'reason' => 'Photo illisible',
        ]);
    }

    public function test_deadline_command_reminds_then_removes_unverified_offers(): void
    {
        $unverified = $this->publishAs(User::factory()->create());
        $verifiedTraveller = User::factory()->create();
        $verifiedTraveller->forceFill(['diaspo_verification_status' => 'verified'])->save();
        $verified = $this->publishAs($verifiedTraveller);

        // J-1 : rappel unique.
        $unverified->forceFill(['verification_deadline_at' => now()->addDay()])->save();
        $this->artisan('diaspo:enforce-verification-deadline')->assertSuccessful();
        $this->artisan('diaspo:enforce-verification-deadline')->assertSuccessful();
        $this->assertNotNull($unverified->fresh()->verification_reminder_sent_at);
        $this->assertSame(1, DiaspoVerificationEvent::where('event', DiaspoVerificationEvent::DEADLINE_REMINDER_SENT)->count());
        $this->assertNotNull(DiaspoOffer::find($unverified->id));

        // Échéance dépassée : retrait, trace conservée.
        $unverified->forceFill(['verification_deadline_at' => now()->subMinute()])->save();
        $this->artisan('diaspo:enforce-verification-deadline')->assertSuccessful();

        $this->assertNull(DiaspoOffer::find($unverified->id));
        $removed = DiaspoOffer::withTrashed()->find($unverified->id);
        $this->assertSame(DiaspoVerificationService::DEADLINE_REMOVAL_REASON, $removed->removal_reason);
        $this->assertDatabaseHas('diaspo_verification_events', [
            'diaspo_offer_id' => $unverified->id, 'event' => DiaspoVerificationEvent::OFFER_REMOVED_DEADLINE,
        ]);
        $this->assertNotNull(DiaspoOffer::find($verified->id));
    }

    public function test_admin_sets_grace_days_and_extends_a_deadline(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)
            ->post(route('admin.diaspo.verifications.settings'), ['grace_days' => 14])
            ->assertRedirect();
        $this->assertSame(14, DiaspoVerificationService::graceDays());

        $offer = $this->publishAs(User::factory()->create());
        $before = $offer->verification_deadline_at;

        $this->actingAs($admin)
            ->post(route('admin.diaspo.offers.extend-deadline', $offer->id), ['days' => 5])
            ->assertRedirect();

        $this->assertEqualsWithDelta($before->addDays(5)->timestamp, $offer->fresh()->verification_deadline_at->timestamp, 5);
        $this->assertDatabaseHas('diaspo_verification_events', [
            'diaspo_offer_id' => $offer->id, 'event' => DiaspoVerificationEvent::DEADLINE_EXTENDED, 'actor_id' => $admin->id,
        ]);
    }

    public function test_admin_pages_show_unverified_profiles_and_history(): void
    {
        $traveller = User::factory()->create(['first_name' => 'Awa']);
        $offer = $this->publishAs($traveller);
        $admin = $this->admin();

        $this->actingAs($admin)
            ->get(route('admin.diaspo.verifications.index', ['status' => 'unverified']))
            ->assertOk()
            ->assertSee('Awa')
            ->assertSee('Profil non vérifié');

        $this->actingAs($admin)
            ->get(route('admin.diaspo.verifications.show', $traveller->id))
            ->assertOk()
            ->assertSee('Historique de vérification')
            ->assertSee('Offre publiée — profil non vérifié');

        $this->actingAs($admin)
            ->get(route('admin.diaspo.offers.show', $offer->id))
            ->assertOk()
            ->assertSee('Échéance de régularisation');

        // Offre retirée : la fiche reste consultable pour la trace.
        $offer->forceFill(['verification_deadline_at' => now()->subMinute()])->save();
        $this->artisan('diaspo:enforce-verification-deadline');
        $this->actingAs($admin)
            ->get(route('admin.diaspo.offers.show', $offer->id))
            ->assertOk()
            ->assertSee('Offre retirée');
        $this->actingAs($admin)
            ->get(route('admin.diaspo.offers.index', ['status' => 'removed']))
            ->assertOk()
            ->assertSee('Retirée');
    }
}
