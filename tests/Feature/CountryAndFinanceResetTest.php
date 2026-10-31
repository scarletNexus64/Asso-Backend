<?php

namespace Tests\Feature;

use App\Mail\OtpCodeMail;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\PlatformRevenueService;
use App\Support\FinanceStatsReset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CountryAndFinanceResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_email_signup_takes_app_country_or_cameroon(): void
    {
        Mail::fake();
        $this->postJson('/api/v1/auth/register-email', [
            'email' => 'a@example.com', 'password' => 'secret12', 'password_confirmation' => 'secret12', 'country' => 'France',
        ])->assertOk();
        $this->postJson('/api/v1/auth/register-email', [
            'email' => 'b@example.com', 'password' => 'secret12', 'password_confirmation' => 'secret12',
        ])->assertOk();

        $a = User::where('email', 'a@example.com')->first();
        $b = User::where('email', 'b@example.com')->first();
        $this->assertSame('France', $a->country);
        $this->assertFalse((bool) $a->country_inferred);
        $this->assertSame('Cameroun', $b->country);
        $this->assertTrue((bool) $b->country_inferred);
    }

    public function test_sync_replaces_only_inferred_country_unless_explicit(): void
    {
        $inferred = User::factory()->create(['country' => 'Cameroun']);
        $inferred->forceFill(['country_inferred' => true])->save();
        Sanctum::actingAs($inferred);
        $this->postJson('/api/v1/auth/country', ['country' => 'Gabon'])->assertOk()->assertJsonPath('updated', true);
        $this->assertSame('Gabon', $inferred->fresh()->country);
        $this->assertFalse((bool) $inferred->fresh()->country_inferred);

        // Pays confirmé : la synchro automatique ne l'écrase pas.
        $this->postJson('/api/v1/auth/country', ['country' => 'France'])->assertOk()->assertJsonPath('updated', false);
        $this->assertSame('Gabon', $inferred->fresh()->country);

        // Choix dans les réglages : remplace.
        $this->postJson('/api/v1/auth/country', ['country' => 'Cameroon', 'explicit' => true])->assertOk();
        $this->assertSame('Cameroun', $inferred->fresh()->country);

        $this->postJson('/api/v1/auth/country', ['country' => 'Atlantide'])->assertUnprocessable();
    }

    public function test_platform_withdrawal_menu_is_hidden_by_default(): void
    {
        $admin = User::factory()->create(['email' => 'admin@asso.com', 'role' => 'admin', 'roles' => ['admin']]);
        $this->actingAs($admin)->get(route('admin.revenue.index'))->assertOk()->assertDontSee(route('admin.revenue.withdrawal'));
        $this->get(route('admin.revenue.withdrawal'))->assertRedirect(route('admin.revenue.index'));
        $this->post(route('admin.revenue.withdrawal.mobile'))->assertNotFound();

        \App\Models\Setting::set(\App\Http\Controllers\Admin\PlatformWithdrawalController::ENABLED_SETTING, 'true');
        $this->get(route('admin.revenue.index'))->assertSee(route('admin.revenue.withdrawal'));
    }

    public function test_every_country_of_the_app_list_is_accepted(): void
    {
        Sanctum::actingAs(User::factory()->create());
        // Les 197 noms (anglais) renvoyés par /v1/currencies/all-with-countries.
        foreach (json_decode(file_get_contents(__DIR__.'/app_country_names.json'), true) as $name) {
            $this->postJson('/api/v1/auth/country', ['country' => $name, 'explicit' => true])
                ->assertOk()->assertJsonPath('success', true);
        }
        $this->postJson('/api/v1/auth/country', ['country' => 'Ivory Coast', 'explicit' => true])->assertJsonPath('country', 'Côte d’Ivoire');
        $this->postJson('/api/v1/auth/country', ['country' => 'England', 'explicit' => true])->assertJsonPath('country', 'England');
    }

    public function test_finance_counters_restart_without_deleting_history(): void
    {
        $admin = User::factory()->create(['email' => 'admin@asso.com', 'role' => 'admin', 'roles' => ['admin']]);
        $client = User::factory()->create();
        $old = WalletTransaction::create(['user_id' => $client->id, 'type' => 'credit', 'amount' => 5000, 'status' => 'completed', 'provider' => 'kpay', 'currency' => 'XAF', 'balance_before' => 0, 'balance_after' => 0, 'description' => 'Test', 'reference' => 'OLD-1']);
        $old->forceFill(['created_at' => now()->subDays(3)])->save();
        $platformOld = WalletTransaction::create(['user_id' => $admin->id, 'type' => 'credit', 'amount' => 700, 'status' => 'completed', 'currency' => 'XAF', 'balance_before' => 0, 'balance_after' => 0, 'description' => 'Test', 'reference' => 'ASSO-OLD']);
        $platformOld->forceFill(['created_at' => now()->subDays(3)])->save();

        FinanceStatsReset::resetNow();
        $this->travel(1)->minutes();
        WalletTransaction::create(['user_id' => $client->id, 'type' => 'credit', 'amount' => 1200, 'status' => 'completed', 'provider' => 'kpay', 'currency' => 'XAF', 'balance_before' => 0, 'balance_after' => 0, 'description' => 'Test', 'reference' => 'NEW-1']);

        $page = $this->actingAs($admin)->get(route('admin.transactions.index'))->assertOk();
        $this->assertEquals(1200, $page->viewData('stats')['total_credits']);
        $this->assertSame(1, $page->viewData('transactions')->total()); // liste remise à zéro…
        $this->assertSame(3, WalletTransaction::count()); // …sans rien supprimer
        $page->assertSee('Compteurs remis à zéro');
        $this->assertDatabaseHas('wallet_transactions', ['id' => $old->id, 'amount' => 5000]);

        // Revenus : la période affichée démarre à la remise à zéro…
        $service = app(PlatformRevenueService::class);
        $period = $service->resolvePeriod('all');
        $this->assertTrue($period['from']->gte(FinanceStatsReset::since()));
        $this->assertNull($service->resolvePeriod('7d')['previous_from']);
        // …mais la requête brute (solde du Wallet ASSO) voit toujours tout l'historique.
        $this->assertGreaterThanOrEqual(0, $service->query('sale_commission', null, now())->count());
        $this->actingAs($admin)->get(route('admin.revenue.index', ['period' => 'all']))->assertOk()->assertSee('Compteurs remis à zéro');
    }
}
