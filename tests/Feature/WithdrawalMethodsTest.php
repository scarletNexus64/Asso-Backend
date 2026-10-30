<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\PayoutAccount;
use App\Models\PlatformWithdrawal;
use App\Models\Setting;
use App\Models\User;
use App\Models\WalletBalance;
use App\Models\WalletTransaction;
use App\Services\FirebaseMessagingService;
use App\Services\IbanValidator;
use App\Services\KPayService;
use App\Services\StripeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

/**
 * Retraits par méthode : carte → IBAN et Mobile Money → numéro partent seuls ;
 * les retraits croisés et les IBAN non pris en charge par Stripe deviennent des
 * demandes (≈ 48 h) réglées depuis le back-office. Frais par méthode.
 */
class WithdrawalMethodsTest extends TestCase
{
    use RefreshDatabase;

    private User $platform;

    protected function setUp(): void
    {
        parent::setUp();

        $fcm = Mockery::mock(FirebaseMessagingService::class);
        $fcm->shouldReceive('sendToUser')->andReturn(['success' => true]);
        $this->app->instance(FirebaseMessagingService::class, $fcm);

        $this->platform = User::factory()->create(['email' => 'plateforme@asso.test']);
        Setting::set('platform_account_email', 'plateforme@asso.test');
    }

    private function kpay(bool $expectDisbursement, ?float $expectedAmount = null): void
    {
        $kpay = Mockery::mock(KPayService::class);
        $kpay->shouldReceive('isConfigured')->andReturn(true);
        if ($expectDisbursement) {
            $kpay->shouldReceive('initiateDisbursement')->once()
                ->withArgs(fn ($p) => $expectedAmount === null || abs($p['amount'] - $expectedAmount) < 0.01)
                ->andReturn(['success' => true, 'id' => 'wdr_1', 'reference' => 'ref_1', 'data' => []]);
        } else {
            $kpay->shouldReceive('initiateDisbursement')->never();
        }
        $this->app->instance(KPayService::class, $kpay);
    }

    private function seller(float $mobile = 0, float $card = 0): User
    {
        $user = User::factory()->create();
        $user->creditKpay('XAF', $mobile + $card);
        WalletBalance::addCardFunds($user->id, 'XAF', $card);

        return $user;
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'admin'])->saveQuietly();

        return $admin;
    }

    // ---------------------------------------------------------------- IBAN

    public function test_iban_check_distinguishes_stripe_supported_and_other_banks(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/wallet/iban/check', ['iban' => 'fr76 3000 6000 0112 3456 7890 189'])
            ->assertOk()
            ->assertJsonPath('data.valid', true)
            ->assertJsonPath('data.stripe_supported', true)
            ->assertJsonPath('data.next_step', 'stripe_onboarding');

        $this->postJson('/api/v1/wallet/iban/check', ['iban' => 'CM21 1000 5000 0112 3456 7890 163'])
            ->assertOk()
            ->assertJsonPath('data.valid', true)
            ->assertJsonPath('data.stripe_supported', false)
            ->assertJsonPath('data.next_step', 'recommend_mobile_money');

        $this->postJson('/api/v1/wallet/iban/check', ['iban' => 'FR76 3000 6000 0112 3456 7890 188'])
            ->assertOk()
            ->assertJsonPath('data.valid', false)
            ->assertJsonPath('data.reason', 'checksum');

        $this->postJson('/api/v1/wallet/iban/check', ['iban' => 'FR76 3000'])
            ->assertJsonPath('data.valid', false);
    }

    public function test_stripe_country_list_is_configurable(): void
    {
        $this->assertTrue(IbanValidator::check('FR7630006000011234567890189')['stripe_supported']);
        Setting::set('stripe_iban_countries', 'DE,BE');
        $this->assertFalse(IbanValidator::check('FR7630006000011234567890189')['stripe_supported']);
    }

    public function test_manual_iban_is_saved_encrypted_and_invalid_one_refused(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->putJson('/api/v1/wallet/bank-account', ['iban' => 'FR76 3000 6000 0112 3456 7890 188', 'account_holder' => 'Awa Test'])
            ->assertStatus(422);

        $this->putJson('/api/v1/wallet/bank-account', ['iban' => 'cm21 1000 5000 0112 3456 7890 163', 'account_holder' => 'Awa Test', 'bic' => 'AFRICMCX'])
            ->assertOk()
            ->assertJsonPath('data.iban_last4', '0163')
            ->assertJsonPath('data.country', 'CM')
            ->assertJsonPath('data.stripe_supported', false)
            ->assertJsonMissingPath('data.iban');

        $account = PayoutAccount::where('user_id', $user->id)->where('type', 'bank_transfer')->firstOrFail();
        $this->assertSame('CM2110005000011234567890163', $account->iban);
        $this->assertStringNotContainsString('CM2110005000', (string) \DB::table('payout_accounts')->where('id', $account->id)->value('iban'));
    }

    // ---------------------------------------------------------------- Mobile Money

    public function test_mobile_funds_withdrawn_automatically_with_fee(): void
    {
        Setting::set('withdrawal_fee_mobile_percent', '2');
        Setting::set('withdrawal_fee_mobile_fixed', '100');
        $this->kpay(true, 9700); // 10 000 - (2 % + 100)

        $user = $this->seller(mobile: 20000);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/wallet/withdraw/kpay', ['amount' => 10000, 'provider' => 'MTN_MOMO_CMR', 'phone' => '+237 699 000 000'])
            ->assertOk()
            ->assertJsonPath('data.processing_mode', 'automatic')
            ->assertJsonPath('data.fee', 300);

        $w = PlatformWithdrawal::where('user_id', $user->id)->firstOrFail();
        $this->assertSame('kpay', $w->provider);
        $this->assertSame('237699000000', $w->payment_account);
        $this->assertEqualsWithDelta(9700, (float) $w->amount_sent, 0.01);
        $this->assertEqualsWithDelta(10000, $user->fresh()->kpayAvailableFor('XAF'), 0.01);
    }

    public function test_card_funds_to_mobile_money_become_a_manual_request(): void
    {
        $this->kpay(false);
        $user = $this->seller(mobile: 1000, card: 50000);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/wallet/withdraw/preview', ['method' => 'mobile_money', 'amount' => 20000, 'provider' => 'MTN_MOMO_CMR'])
            ->assertOk()
            ->assertJsonPath('data.processing_mode', 'manual')
            ->assertJsonPath('data.reason', 'card_funds_to_mobile')
            ->assertJsonPath('data.delay_hours', 48);

        $this->postJson('/api/v1/wallet/withdraw/kpay', ['amount' => 20000, 'provider' => 'MTN_MOMO_CMR', 'phone' => '237699000000'])
            ->assertOk()
            ->assertJsonPath('data.processing_mode', 'manual')
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.delay_hours', 48);

        $w = PlatformWithdrawal::where('user_id', $user->id)->firstOrFail();
        $this->assertSame('manual', $w->provider);
        $this->assertSame('mobile_money', $w->method);
        $this->assertSame('mixed', $w->funds_origin);
        $this->assertNotNull($w->expected_by);

        // Débité tout de suite ; la part mobile part en premier, le reste vient de la carte.
        $row = WalletBalance::where('user_id', $user->id)->where('currency', 'XAF')->first();
        $this->assertEqualsWithDelta(31000, $row->available, 0.01);
        $this->assertEqualsWithDelta(31000, $row->card_available, 0.01);
        $this->assertEqualsWithDelta(0, $row->mobile_available, 0.01);
    }

    // ---------------------------------------------------------------- Virement

    public function test_mobile_funds_to_stripe_iban_become_a_manual_request(): void
    {
        $this->mock(StripeService::class, function ($mock) {
            $mock->shouldReceive('isConfigured')->andReturn(true);
            $mock->shouldReceive('payoutToVendor')->never();
            $mock->shouldReceive('assertPayoutPossible')->never();
        });

        $user = $this->seller(mobile: 100000);
        $user->forceFill(['stripe_account_id' => 'acct_1', 'stripe_account_status' => 'approved', 'stripe_bank_country' => 'FR', 'stripe_external_last4' => '0189'])->saveQuietly();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/wallet/withdraw/bank', ['amount' => 60000, 'currency' => 'XAF'])
            ->assertOk()
            ->assertJsonPath('data.processing_mode', 'manual')
            ->assertJsonPath('data.reason', 'mobile_funds_to_bank');

        $w = PlatformWithdrawal::where('user_id', $user->id)->firstOrFail();
        $this->assertSame('stripe_connect', $w->payment_method);
        $this->assertSame('bank_transfer', $w->method);
        $this->assertEqualsWithDelta(40000, $user->fresh()->kpayAvailableFor('XAF'), 0.01);
    }

    public function test_non_stripe_iban_withdrawal_is_a_manual_request_with_bank_fee(): void
    {
        Setting::set('withdrawal_fee_bank_percent', '1');
        $user = $this->seller(card: 100000);
        PayoutAccount::create([
            'user_id' => $user->id, 'type' => 'bank_transfer', 'iban' => 'CM2110005000011234567890163',
            'iban_last4' => '0163', 'country' => 'CM', 'account_holder' => 'Awa Test',
        ]);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/wallet/withdraw/bank', ['amount' => 1000])
            ->assertStatus(422); // sous le minimum d'un virement manuel (5 000 FCFA)

        $this->postJson('/api/v1/wallet/withdraw/bank', ['amount' => 50000])
            ->assertOk()
            ->assertJsonPath('data.processing_mode', 'manual')
            ->assertJsonPath('data.reason', 'iban_not_stripe')
            ->assertJsonPath('data.fee', 500)
            ->assertJsonPath('data.net_amount', 49500);

        $w = PlatformWithdrawal::where('user_id', $user->id)->firstOrFail();
        $this->assertSame('CM21 1000 5000 0112 3456 7890 163', $w->payment_account);
        $this->assertSame('card', $w->funds_origin);
        $this->assertEqualsWithDelta(50000, (float) $w->card_funds_used, 0.01);
    }

    public function test_bank_withdrawal_requires_an_iban(): void
    {
        $user = $this->seller(mobile: 100000);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/wallet/withdraw/bank', ['amount' => 10000])
            ->assertStatus(422);
        $this->assertSame(0, PlatformWithdrawal::count());
    }

    // ---------------------------------------------------------------- Traitement

    public function test_admin_marks_request_paid_and_fee_goes_to_asso_once(): void
    {
        Setting::set('withdrawal_fee_mobile_fixed', '200');
        $this->kpay(false);
        $user = $this->seller(card: 30000);
        Sanctum::actingAs($user);
        $this->postJson('/api/v1/wallet/withdraw/kpay', ['amount' => 10000, 'provider' => 'ORANGE_CMR', 'phone' => '237655000000'])->assertOk();
        $w = PlatformWithdrawal::firstOrFail();

        $admin = $this->admin();
        $this->actingAs($admin)->post("/admin/payouts/requests/{$w->id}/take")->assertRedirect();
        $this->assertSame('processing', $w->fresh()->status);

        $this->actingAs($admin)->post("/admin/payouts/requests/{$w->id}/complete", ['payout_reference' => 'OM-123'])->assertRedirect();
        $w->refresh();
        $this->assertSame('completed', $w->status);
        $this->assertSame('OM-123', $w->payout_reference);
        $this->assertSame($admin->id, $w->processed_by);
        $this->assertSame('completed', WalletTransaction::where('reference_type', 'platform_withdrawal')->where('reference_id', $w->id)->value('status'));
        $this->assertEqualsWithDelta(200, $this->platform->fresh()->kpayBalanceFor('XAF'), 0.01);

        // Deuxième clôture : sans effet, frais non recrédités.
        $this->actingAs($admin)->post("/admin/payouts/requests/{$w->id}/complete", ['payout_reference' => 'OM-123']);
        $this->assertEqualsWithDelta(200, $this->platform->fresh()->kpayBalanceFor('XAF'), 0.01);
    }

    public function test_admin_rejects_request_and_wallet_is_refunded_with_card_share(): void
    {
        $user = $this->seller(card: 80000);
        PayoutAccount::create(['user_id' => $user->id, 'type' => 'bank_transfer', 'iban' => 'CM2110005000011234567890163', 'iban_last4' => '0163', 'country' => 'CM']);
        Sanctum::actingAs($user);
        $this->postJson('/api/v1/wallet/withdraw/bank', ['amount' => 50000])->assertOk();
        $w = PlatformWithdrawal::firstOrFail();
        $this->assertEqualsWithDelta(30000, WalletBalance::where('user_id', $user->id)->value('card_funds'), 0.01);

        $this->actingAs($this->admin())->post("/admin/payouts/requests/{$w->id}/reject", ['reason' => 'IBAN au nom d’un tiers'])->assertRedirect();

        $this->assertSame('failed', $w->fresh()->status);
        $row = WalletBalance::where('user_id', $user->id)->where('currency', 'XAF')->first();
        $this->assertEqualsWithDelta(80000, $row->available, 0.01);
        $this->assertEqualsWithDelta(80000, $row->card_available, 0.01);
        $this->assertEqualsWithDelta(0, $this->platform->fresh()->kpayBalanceFor('XAF'), 0.01);
    }

    public function test_admin_sends_mobile_request_through_gateway(): void
    {
        $user = $this->seller(card: 30000);
        Sanctum::actingAs($user);

        // Fonds carte : la demande est créée sans envoi ; l'admin l'envoie ensuite (net = 10 000).
        $kpay = Mockery::mock(KPayService::class);
        $kpay->shouldReceive('isConfigured')->andReturn(true);
        $kpay->shouldReceive('initiateDisbursement')->once()
            ->withArgs(fn ($p) => abs($p['amount'] - 10000) < 0.01 && $p['phone_number'] === '237699000000')
            ->andReturn(['success' => true, 'id' => 'wdr_9', 'reference' => 'r', 'data' => []]);
        $this->app->instance(KPayService::class, $kpay);

        $this->postJson('/api/v1/wallet/withdraw/kpay', ['amount' => 10000, 'provider' => 'MTN_MOMO_CMR', 'phone' => '237699000000'])->assertOk();
        $w = PlatformWithdrawal::firstOrFail();
        $this->assertSame('manual', $w->provider);

        $this->actingAs($this->admin())->post("/admin/payouts/requests/{$w->id}/execute")->assertRedirect()->assertSessionHas('success');
        $w->refresh();
        $this->assertSame('kpay', $w->provider);
        $this->assertSame('processing', $w->status);
        $this->assertSame('wdr_9', $w->kpay_reference);
    }

    public function test_user_cancels_pending_request(): void
    {
        $this->kpay(false);
        $user = $this->seller(card: 30000);
        Sanctum::actingAs($user);
        $this->postJson('/api/v1/wallet/withdraw/kpay', ['amount' => 10000, 'provider' => 'MTN_MOMO_CMR', 'phone' => '237699000000'])->assertOk();
        $w = PlatformWithdrawal::firstOrFail();

        $this->postJson("/api/v1/wallet/withdrawals/{$w->id}/cancel")->assertOk()->assertJsonPath('data.status', 'cancelled');
        $this->assertEqualsWithDelta(30000, $user->fresh()->kpayAvailableFor('XAF'), 0.01);
        $this->postJson("/api/v1/wallet/withdrawals/{$w->id}/cancel")->assertStatus(409);
    }

    public function test_manager_cannot_process_requests(): void
    {
        $this->kpay(false);
        $user = $this->seller(card: 30000);
        Sanctum::actingAs($user);
        $this->postJson('/api/v1/wallet/withdraw/kpay', ['amount' => 10000, 'provider' => 'MTN_MOMO_CMR', 'phone' => '237699000000'])->assertOk();
        $w = PlatformWithdrawal::firstOrFail();

        $manager = User::factory()->create();
        $manager->forceFill(['role' => 'product_manager', 'roles' => ['product_manager'], 'admin_permissions' => ['payouts']])->saveQuietly();
        $this->actingAs($manager)->post("/admin/payouts/requests/{$w->id}/complete", ['payout_reference' => 'X'])->assertForbidden();
        $this->assertSame('pending', $w->fresh()->status);
    }

    // ---------------------------------------------------------------- Origine des fonds

    public function test_other_debits_consume_mobile_money_funds_first(): void
    {
        $user = $this->seller(mobile: 10000, card: 20000);
        $user->debitKpay('XAF', 15000); // achat payé avec le portefeuille

        $row = WalletBalance::where('user_id', $user->id)->first();
        $this->assertEqualsWithDelta(15000, $row->card_available, 0.01);
        $this->assertEqualsWithDelta(0, $row->mobile_available, 0.01);
    }

    public function test_card_paid_ratio(): void
    {
        $order = new Order(['payment_status' => Order::PAYMENT_PAID, 'payment_method' => 'stripe_direct', 'total' => 1000]);
        $this->assertSame(1.0, $order->cardPaidRatio());

        $order = new Order(['payment_status' => Order::PAYMENT_PAID, 'payment_method' => 'kpay_direct', 'total' => 1000]);
        $this->assertSame(0.0, $order->cardPaidRatio());
    }

    // ---------------------------------------------------------------- Back-office

    public function test_admin_pages_list_requests_accounts_and_save_fees(): void
    {
        $this->kpay(false);
        $user = $this->seller(card: 30000);
        $user->forceFill(['first_name' => 'Mariam', 'last_name' => 'Vendeuse'])->saveQuietly();
        PayoutAccount::create(['user_id' => $user->id, 'type' => 'mobile_money', 'provider' => 'MTN_MOMO_CMR', 'phone_number' => '237699000000', 'currency' => 'XAF']);
        Sanctum::actingAs($user);
        $this->postJson('/api/v1/wallet/withdraw/kpay', ['amount' => 10000, 'provider' => 'MTN_MOMO_CMR', 'phone' => '237699000000'])->assertOk();

        $admin = $this->admin();
        $this->actingAs($admin)->get('/admin/payouts/requests?status=pending&method=mobile_money')
            ->assertOk()->assertSee('Mariam')->assertSee('Demande manuelle')->assertSee('Envoyer via Mobile Money');
        $this->actingAs($admin)->get('/admin/payouts/requests?status=pending&method=bank_transfer')
            ->assertOk()->assertDontSee('Mariam');
        $this->actingAs($admin)->get('/admin/payouts/accounts?mobile=yes')
            ->assertOk()->assertSee('Mariam')->assertSee('237699000000');
        $this->actingAs($admin)->get('/admin/payouts/accounts?iban=manual')
            ->assertOk()->assertDontSee('Mariam');

        $this->actingAs($admin)->post('/admin/payouts/fees', [
            'withdrawal_fee_mobile_percent' => 1.5,
            'withdrawal_fee_mobile_fixed' => 50,
            'withdrawal_fee_bank_percent' => 2,
            'withdrawal_fee_bank_fixed' => 0,
            'withdrawal_manual_delay_hours' => 48,
            'min_manual_bank_withdrawal_amount' => 5000,
            'stripe_iban_countries' => 'fr, be ,de',
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertSame('1.5', (string) Setting::get('withdrawal_fee_mobile_percent'));
        $this->assertSame('FR,BE,DE', Setting::get('stripe_iban_countries'));
    }
}
