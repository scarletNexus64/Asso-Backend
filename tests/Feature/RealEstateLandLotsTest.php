<?php

namespace Tests\Feature;

use App\Models\RealEstate\Cabinet;
use App\Models\RealEstate\Listing;
use App\Models\RealEstate\Reservation;
use App\Models\RealEstate\Unit;
use App\Models\Setting;
use App\Models\User;
use App\Services\FirebaseMessagingService;
use App\Services\RealEstate\RealEstateReservationService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Terrain vendu au morceau : l'acheteur prend N morceaux d'un lot, ou tout
 * ce qui reste du terrain, en une seule réservation.
 */
class RealEstateLandLotsTest extends TestCase
{
    use RefreshDatabase;

    private User $tenant;

    private User $cabinetUser;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Storage::fake('local');
        $this->mock(FirebaseMessagingService::class, fn ($m) => $m->shouldReceive('sendToUser')->andReturn([]));
        User::factory()->create(['email' => 'admin@asso.com']);
        $partner = User::factory()->create();
        $cabinet = Cabinet::create(['name' => 'Cabinet partenaire', 'user_id' => $partner->id]);
        Setting::set('immo_default_cabinet_id', $cabinet->id, 'integer');
        Setting::set('immo_asso_markup_rate', '10', 'string');
        Setting::set('immo_partner_commission_rate', '5', 'string');
        $this->tenant = User::factory()->create();
        $this->cabinetUser = User::factory()->create();
    }

    /** Terrain avec un lot répété (7 × 1 000 m²) ou des lots différents. */
    private function land(array $units): Listing
    {
        $cabinet = Cabinet::create(['name' => 'Cabinet du terrain', 'user_id' => $this->cabinetUser->id]);
        $land = Listing::create([
            'type' => 'land', 'cabinet_id' => $cabinet->id, 'title' => 'Terrain Manassa', 'city' => 'Yaoundé',
            'deposit_percent' => 10, 'status' => 'published', 'published_at' => now(),
        ]);
        foreach ($units as $u) {
            $land->units()->create($u);
        }

        return $land->fresh('units');
    }

    private function quote(Listing $land, array $query)
    {
        Sanctum::actingAs($this->tenant);

        return $this->getJson("/api/v1/real-estate/listings/{$land->id}/quote?".http_build_query($query));
    }

    private function book(Listing $land, array $body, string $mode = 'wallet')
    {
        Sanctum::actingAs($this->tenant);

        return $this->postJson("/api/v1/real-estate/listings/{$land->id}/reservations", $body + [
            'contract_accepted' => true, 'payment_mode' => $mode,
        ] + ($mode === 'kpay_direct' ? ['provider' => 'mtn', 'phone_number' => '670000000'] : []));
    }

    public function test_quote_for_several_identical_lots_adds_up_their_prices(): void
    {
        $land = $this->land([['label' => '1 000 m²', 'surface_min' => 1000, 'net_price' => 20000000, 'units_total' => 8, 'units_available' => 7]]);
        $unit = $land->units->first();

        // 3 × 20 M = 60 M ; acompte 10 % = 6 M ; frais 10 % + 5 % de l'acompte.
        $this->quote($land, ['unit_id' => $unit->id, 'quantity' => 3])->assertOk()
            ->assertJsonPath('data.unit_price', 60000000)
            ->assertJsonPath('data.base_amount', 6000000)
            ->assertJsonPath('data.total', 6900000)
            ->assertJsonPath('data.quantity', 3)
            ->assertJsonPath('data.unit_id', $unit->id)
            ->assertJsonPath('data.lots.0.quantity', 3)
            ->assertJsonPath('data.lots.0.unit_price', 20000000);

        // Un seul lot : montant inchangé par rapport à avant.
        $this->quote($land, ['unit_id' => $unit->id])->assertOk()
            ->assertJsonPath('data.unit_price', 20000000)
            ->assertJsonPath('data.total', 2300000)
            ->assertJsonPath('data.quantity', 1);

        $this->quote($land, ['unit_id' => $unit->id, 'quantity' => 8])->assertStatus(422)
            ->assertJsonPath('code', 'lots_unavailable');
    }

    public function test_whole_quote_covers_every_lot_still_for_sale(): void
    {
        $land = $this->land([
            ['label' => 'Parcelle 300 m²', 'surface_min' => 300, 'net_price' => 2500000, 'units_total' => 1, 'units_available' => 1],
            ['label' => 'Parcelle 400 m²', 'surface_min' => 400, 'net_price' => 3000000, 'units_total' => 1, 'units_available' => 1],
            ['label' => 'Parcelle vendue', 'surface_min' => 500, 'net_price' => 4000000, 'units_total' => 1, 'units_available' => 0],
        ]);

        $this->quote($land, ['whole' => 1])->assertOk()
            ->assertJsonPath('data.unit_price', 5500000)
            ->assertJsonPath('data.base_amount', 550000)
            ->assertJsonPath('data.total', 632500)
            ->assertJsonPath('data.quantity', 2)
            ->assertJsonPath('data.whole', true)
            ->assertJsonPath('data.unit_id', null);

        $this->getJson("/api/v1/real-estate/listings/{$land->id}")->assertOk()
            ->assertJsonPath('data.subdivision.can_buy_whole', true)
            ->assertJsonPath('data.subdivision.whole_payment.lot_price', 5500000)
            ->assertJsonPath('data.subdivision.whole_payment.total_to_pay', 632500)
            ->assertJsonPath('data.subdivision.whole_payment.balance_due', 4950000);
    }

    public function test_booking_several_lots_takes_them_all_from_stock(): void
    {
        $land = $this->land([['label' => '1 000 m²', 'surface_min' => 1000, 'net_price' => 20000000, 'units_total' => 8, 'units_available' => 7]]);
        $unit = $land->units->first();
        app(WalletService::class)->credit($this->tenant, 7000000);

        $this->book($land, ['unit_id' => $unit->id, 'quantity' => 3, 'expected_total' => 6900000])->assertCreated()
            ->assertJsonPath('data.status', 'paid')
            ->assertJsonPath('data.total_amount', 6900000)
            ->assertJsonPath('data.unit_price', 60000000)
            ->assertJsonPath('data.quantity', 3)
            ->assertJsonPath('data.unit_label', '3 × 1 000 m²');

        $this->assertSame(4, $unit->fresh()->units_available);
        $r = Reservation::firstOrFail();
        $this->assertSame($unit->id, $r->unit_id);
        $this->assertSame([$unit->id => 3], $r->lotQuantities());
        Storage::disk('local')->assertExists($r->contract_path);
    }

    public function test_buying_the_whole_land_closes_every_lot(): void
    {
        $land = $this->land([
            ['label' => 'Parcelle 300 m²', 'surface_min' => 300, 'net_price' => 2500000, 'units_total' => 1, 'units_available' => 1],
            ['label' => 'Parcelle 400 m²', 'surface_min' => 400, 'net_price' => 3000000, 'units_total' => 1, 'units_available' => 1],
        ]);
        app(WalletService::class)->credit($this->tenant, 700000);

        $this->book($land, ['whole' => true, 'expected_total' => 632500])->assertCreated()
            ->assertJsonPath('data.status', 'paid')
            ->assertJsonPath('data.whole', true)
            ->assertJsonPath('data.quantity', 2)
            ->assertJsonPath('data.unit_label', 'Tout le terrain (2 morceaux)')
            ->assertJsonCount(2, 'data.lots');

        $this->assertSame([0, 0], $land->units()->orderBy('id')->pluck('units_available')->all());
        $r = Reservation::firstOrFail();
        $this->assertNull($r->unit_id);
        // Les deux lots restent protégés contre la suppression dans l'admin.
        $this->assertEqualsCanonicalizing($land->units->pluck('id')->all(), array_keys(Reservation::reservedUnitCounts($land->id)));

        // Plus rien à vendre : « tout acheter » est refusé.
        $this->book($land, ['whole' => true])->assertStatus(422);
    }

    public function test_more_lots_than_available_is_refused_before_paying(): void
    {
        $land = $this->land([['label' => '1 000 m²', 'surface_min' => 1000, 'net_price' => 20000000, 'units_total' => 3, 'units_available' => 2]]);
        app(WalletService::class)->credit($this->tenant, 10000000);

        $this->book($land, ['unit_id' => $land->units->first()->id, 'quantity' => 3])->assertStatus(422);
        $this->assertSame(0, Reservation::count());
        $this->assertSame(2, $land->units->first()->fresh()->units_available);
    }

    public function test_lots_sold_during_mobile_money_payment_are_refunded_in_full(): void
    {
        $land = $this->land([['label' => '1 000 m²', 'surface_min' => 1000, 'net_price' => 20000000, 'units_total' => 3, 'units_available' => 3]]);
        $unit = $land->units->first();
        $service = app(RealEstateReservationService::class);
        $r = $service->create($this->tenant, $land, $unit, ['quantity' => 2], 'kpay_direct',
            app(\App\Services\RealEstate\RealEstatePricingService::class)->selection($land, $unit, 2));

        // Un autre acheteur prend 2 morceaux pendant le paiement : il n'en reste qu'un.
        Unit::whereKey($unit->id)->update(['units_available' => 1]);
        $service->confirm($r);

        $this->assertSame('refunded', $r->fresh()->status);
        $this->assertSame(1, $unit->fresh()->units_available);
        $this->assertSame((float) $r->total_amount, (float) $this->tenant->fresh()->kpayBalanceFor('XAF'));
    }
}
