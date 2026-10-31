<?php

namespace Tests\Feature;

use App\Models\PlatformWithdrawal;
use App\Models\ServiceConfiguration;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Retrait ASSO depuis le back-office : le retrait Wallet existant, exécuté au
 * nom du compte plateforme (soldes mobile / bancaire séparés).
 */
class PlatformWithdrawalAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $platform;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        \App\Models\Setting::set(\App\Http\Controllers\Admin\PlatformWithdrawalController::ENABLED_SETTING, 'true');

        ServiceConfiguration::setConfig('elgiopay', ['mode' => 'live', 'public_key' => 'pk_live_x', 'secret_key' => 'sk_live_x'], true);
        Http::fake([
            'api.elgiopay.com/api/v1/payouts' => Http::response(['success' => true, 'data' => ['payout_id' => 'PO1', 'status' => 'processing']]),
            '*' => Http::response([], 500),
        ]);

        $this->platform = User::factory()->create(['email' => 'plateforme@asso.test']);
        Setting::set('platform_account_email', 'plateforme@asso.test');
        $this->platform->creditKpay('XAF', 50000);

        $this->admin = User::factory()->create(['first_name' => 'Awa', 'last_name' => 'Admin']);
        $this->admin->forceFill(['role' => 'admin'])->saveQuietly();
    }

    public function test_admin_sees_balances_per_country_and_bank_balance(): void
    {
        $this->actingAs($this->admin)->get('/admin/revenue/withdrawal')
            ->assertOk()
            ->assertSeeInOrder(['Bénin', 'Cameroun', "Côte d'Ivoire", 'RD Congo', 'Gabon'], false)
            ->assertSee('Solde bancaire')
            ->assertSee('50 000 XAF')
            ->assertSee('Solde XAF commun avec Gabon');
    }

    public function test_operator_outside_asso_countries_is_refused(): void
    {
        $this->actingAs($this->admin)->post('/admin/revenue/withdrawal/mobile', [
            'provider' => 'MPESA_KEN', 'phone' => '700000000', 'amount' => 1000,
        ])->assertRedirect()->assertSessionHas('error');

        $this->assertSame(0, PlatformWithdrawal::count());
        Http::assertNothingSent();
    }

    /** L'IBAN de la plateforme se saisit sur la page Stripe : l'admin y est redirigé. */
    public function test_platform_iban_opens_stripe_hosted_onboarding(): void
    {
        $this->mock(\App\Services\StripeService::class, function ($mock) {
            $mock->shouldReceive('isConfigured')->andReturn(true);
            $mock->shouldReceive('mode')->andReturn('test');
            $mock->shouldReceive('payoutCurrencyForCountry')->with('FR')->andReturn('eur');
            $mock->shouldReceive('createHostedAccount')->once()->andReturn('acct_platform');
            $mock->shouldReceive('createOnboardingLink')->once()
                ->andReturn(['url' => 'https://connect.stripe.com/setup/platform', 'expires_at' => null]);
        });

        $this->actingAs($this->admin)->post('/admin/revenue/withdrawal/iban', ['country' => 'FR'])
            ->assertRedirect('https://connect.stripe.com/setup/platform');

        $this->assertSame('acct_platform', $this->platform->fresh()->stripe_account_id);
    }

    public function test_admin_validates_pending_platform_iban(): void
    {
        $this->platform->forceFill([
            'stripe_account_id' => 'acct_test', 'stripe_account_status' => 'pending', 'stripe_external_last4' => '0189',
        ])->saveQuietly();

        $this->actingAs($this->admin)->post('/admin/revenue/withdrawal/iban/approve')
            ->assertRedirect()->assertSessionHas('success');

        $this->assertSame('approved', $this->platform->fresh()->stripe_account_status);
    }

    public function test_mobile_withdrawal_debits_platform_wallet_only(): void
    {
        $this->admin->creditKpay('XAF', 9000);

        $this->actingAs($this->admin)->post('/admin/revenue/withdrawal/mobile', [
            'provider' => 'ORANGE_CMR', 'phone' => '690000000', 'amount' => 20000, 'notes' => 'Loyer',
        ])->assertRedirect()->assertSessionHas('success');

        $w = PlatformWithdrawal::sole();
        $this->assertSame($this->platform->id, $w->user_id);
        $this->assertSame($this->admin->id, $w->admin_id);
        $this->assertSame('elgiopay:PO1', $w->kpay_reference);
        $this->assertStringContainsString('Loyer', $w->admin_notes);
        $this->assertSame(30000.0, $this->platform->fresh()->kpayAvailableFor('XAF'));
        $this->assertSame(9000.0, $this->admin->fresh()->kpayAvailableFor('XAF'), "le Wallet de l'admin n'est pas touché");

        Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/payouts')
            && $r['payout_method'] === 'orange_money' && $r['amount'] === 20000);
    }

    public function test_withdrawal_above_mobile_balance_is_refused(): void
    {
        $this->actingAs($this->admin)->post('/admin/revenue/withdrawal/mobile', [
            'provider' => 'MTN_MOMO_CMR', 'phone' => '670000000', 'amount' => 60000,
        ])->assertRedirect()->assertSessionHas('error');

        $this->assertSame(0, PlatformWithdrawal::count());
        $this->assertSame(50000.0, $this->platform->fresh()->kpayAvailableFor('XAF'));
        Http::assertNothingSent();
    }

    public function test_bank_withdrawal_needs_validated_iban(): void
    {
        $this->actingAs($this->admin)->post('/admin/revenue/withdrawal/bank', ['amount' => 10000, 'currency' => 'XAF'])
            ->assertRedirect()->assertSessionHas('error');

        $this->assertSame(50000.0, $this->platform->fresh()->kpayAvailableFor('XAF'));
    }

    public function test_product_manager_cannot_withdraw(): void
    {
        $manager = User::factory()->create(['role' => 'product_manager', 'roles' => ['product_manager']]);

        $this->actingAs($manager)->post('/admin/revenue/withdrawal/mobile', [
            'provider' => 'MTN_MOMO_CMR', 'phone' => '670000000', 'amount' => 1000,
        ])->assertForbidden();

        $this->assertSame(0, PlatformWithdrawal::count());
    }
}
