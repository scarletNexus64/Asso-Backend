<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\StripeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Compte de virement vendeur via l'inscription HÉBERGÉE par Stripe.
 *
 * En France, la plateforme n'a pas le droit de collecter elle-même l'identité et
 * l'IBAN : l'app demande un lien vers la page Stripe, puis relit le statut.
 * StripeService est MOCKÉ : aucun appel réel à Stripe.
 */
class StripeConnectOnboardingTest extends TestCase
{
    use RefreshDatabase;

    private function mockStripe(callable $expectations): void
    {
        $this->mock(StripeService::class, function ($mock) use ($expectations) {
            $mock->shouldReceive('isConfigured')->byDefault()->andReturn(true);
            $mock->shouldReceive('payoutCurrencyForCountry')->byDefault()->andReturn('eur');
            $mock->shouldReceive('mode')->byDefault()->andReturn('test');
            $mock->shouldReceive('createOnboardingLink')->byDefault()->andReturn([
                'url' => 'https://connect.stripe.com/setup/e/acct_new/abc',
                'expires_at' => '2026-10-30T12:00:00Z',
            ]);
            $expectations($mock);
        });
    }

    private function state(array $overrides = []): array
    {
        return array_merge([
            'exists' => true,
            'transfers' => 'inactive',
            'payouts_enabled' => false,
            'charges_enabled' => false,
            'requirements_due' => [],
            'disabled_reason' => null,
            'details_submitted' => false,
            'payout_interval' => 'manual',
            'ready' => false,
            'error' => null,
            'missing' => false,
        ], $overrides);
    }

    private function vendor(array $stripe = []): User
    {
        $user = User::factory()->create();
        $user->forceFill($stripe)->saveQuietly();

        return $user;
    }

    public function test_onboarding_creates_hosted_account_and_returns_stripe_link(): void
    {
        $this->mockStripe(function ($mock) {
            $mock->shouldReceive('createHostedAccount')->once()
                ->withArgs(fn (array $data) => $data['country'] === 'FR' && $data['currency'] === 'eur')
                ->andReturn('acct_new');
            $mock->shouldReceive('createOnboardingLink')->once()
                ->withArgs(fn ($account, $refresh, $return) => $account === 'acct_new'
                    && str_contains($refresh, '/stripe/connect/refresh/')
                    && str_contains($refresh, 'signature=')
                    && str_ends_with($return, '/stripe/connect/return'))
                ->andReturn(['url' => 'https://connect.stripe.com/setup/e/acct_new/abc', 'expires_at' => null]);
        });

        $user = $this->vendor();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/stripe/connect/onboarding', ['country' => 'fr'])
            ->assertOk()
            ->assertJsonPath('data.url', 'https://connect.stripe.com/setup/e/acct_new/abc')
            ->assertJsonPath('data.status.has_account', true)
            // L'inscription n'est pas terminée : pas encore « en attente ».
            ->assertJsonPath('data.status.status', null);

        $user->refresh();
        $this->assertSame('acct_new', $user->stripe_account_id);
        $this->assertSame('FR', $user->stripe_bank_country);
    }

    public function test_account_session_creates_account_and_returns_client_secret(): void
    {
        $this->mockStripe(function ($mock) {
            $mock->shouldReceive('keysProblem')->andReturn(null);
            $mock->shouldReceive('publishableKey')->andReturn('pk_test_asso');
            $mock->shouldReceive('createHostedAccount')->once()->andReturn('acct_new');
            $mock->shouldReceive('createOnboardingSession')->once()->with('acct_new')
                ->andReturn(['client_secret' => 'accs_secret_123', 'expires_at' => 1798000000]);
        });

        $user = $this->vendor();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/stripe/connect/account-session', ['country' => 'FR'])
            ->assertOk()
            ->assertJsonPath('data.client_secret', 'accs_secret_123')
            ->assertJsonPath('data.publishable_key', 'pk_test_asso')
            ->assertJsonPath('data.status.has_account', true);

        $this->assertSame('acct_new', $user->fresh()->stripe_account_id);
    }

    /** Session renouvelée par le SDK : pas de pays, compte réutilisé. */
    public function test_account_session_refresh_reuses_account(): void
    {
        $this->mockStripe(function ($mock) {
            $mock->shouldReceive('keysProblem')->andReturn(null);
            $mock->shouldReceive('publishableKey')->andReturn('pk_test_asso');
            $mock->shouldReceive('accountState')->andReturn($this->state());
            $mock->shouldReceive('createHostedAccount')->never();
            $mock->shouldReceive('createOnboardingSession')->once()->with('acct_existing')
                ->andReturn(['client_secret' => 'accs_secret_456', 'expires_at' => null]);
        });

        $this->actingAs($this->vendor(['stripe_account_id' => 'acct_existing']), 'sanctum')
            ->postJson('/api/v1/stripe/connect/account-session')
            ->assertOk()
            ->assertJsonPath('data.client_secret', 'accs_secret_456');
    }

    public function test_account_session_refused_when_keys_are_inconsistent(): void
    {
        $this->mockStripe(function ($mock) {
            $mock->shouldReceive('keysProblem')->andReturn('Clé publique Stripe (pk_…) manquante.');
            $mock->shouldReceive('createHostedAccount')->never();
        });

        $this->actingAs($this->vendor(), 'sanctum')
            ->postJson('/api/v1/stripe/connect/account-session', ['country' => 'FR'])
            ->assertStatus(503)
            ->assertJsonPath('message', __('payments.stripe_connect.error_unavailable'));
    }

    public function test_onboarding_reuses_existing_account(): void
    {
        $this->mockStripe(function ($mock) {
            $mock->shouldReceive('accountState')->andReturn($this->state());
            $mock->shouldReceive('createHostedAccount')->never();
            $mock->shouldReceive('createOnboardingLink')->once()
                ->withArgs(fn ($account) => $account === 'acct_existing')
                ->andReturn(['url' => 'https://connect.stripe.com/x', 'expires_at' => null]);
        });

        $this->actingAs($this->vendor(['stripe_account_id' => 'acct_existing']), 'sanctum')
            ->postJson('/api/v1/stripe/connect/onboarding')
            ->assertOk()
            ->assertJsonPath('data.url', 'https://connect.stripe.com/x');
    }

    public function test_onboarding_requires_a_country_for_a_new_account(): void
    {
        $this->mockStripe(function ($mock) {
            $mock->shouldReceive('createHostedAccount')->never();
        });

        $this->actingAs($this->vendor(), 'sanctum')
            ->postJson('/api/v1/stripe/connect/onboarding', ['country' => 'France'])
            ->assertStatus(422)
            ->assertJsonPath('errors.country.0', __('payments.stripe_connect.validation.country_size'));
    }

    public function test_onboarding_rejects_country_without_supported_payout_currency(): void
    {
        $this->mockStripe(function ($mock) {
            $mock->shouldReceive('payoutCurrencyForCountry')->with('CM')->andReturn(null);
            $mock->shouldReceive('createHostedAccount')->never();
        });

        $this->actingAs($this->vendor(), 'sanctum')
            ->postJson('/api/v1/stripe/connect/onboarding', ['country' => 'CM'])
            ->assertStatus(422)
            ->assertJsonPath('message', __('payments.stripe_connect.error_country_unsupported'));
    }

    /**
     * Erreur de configuration de la PLATEFORME (ex. collecte des informations non
     * acceptée dans Stripe) : jamais présentée comme une erreur du vendeur.
     */
    public function test_platform_config_error_is_not_blamed_on_vendor(): void
    {
        $this->mockStripe(function ($mock) {
            $mock->shouldReceive('createHostedAccount')->andThrow(
                \Stripe\Exception\InvalidRequestException::factory(
                    'Due to regulations in France, you can only create connected accounts using Stripe-hosted onboarding.',
                    400,
                    null,
                    null,
                    null,
                    'account_creation_requirement_collection_unacknowledged',
                )
            );
        });

        $user = $this->vendor();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/stripe/connect/onboarding', ['country' => 'FR'])
            ->assertStatus(503)
            ->assertJsonPath('message', __('payments.stripe_connect.error_unavailable'))
            ->assertJsonMissingPath('errors');

        $this->assertNull($user->fresh()->stripe_account_id);
    }

    public function test_stripe_connection_failure_returns_safe_service_error(): void
    {
        $this->mockStripe(function ($mock) {
            $mock->shouldReceive('createHostedAccount')->andThrow(
                new \Stripe\Exception\ApiConnectionException('Could not resolve host: api.stripe.com')
            );
        });

        $this->actingAs($this->vendor(), 'sanctum')
            ->postJson('/api/v1/stripe/connect/onboarding', ['country' => 'FR'])
            ->assertStatus(503)
            ->assertJsonPath('message', __('payments.stripe_connect.error_connection'));
    }

    public function test_onboarding_returns_503_when_stripe_not_configured(): void
    {
        $this->mockStripe(function ($mock) {
            $mock->shouldReceive('isConfigured')->andReturn(false);
            $mock->shouldReceive('createHostedAccount')->never();
        });

        $this->actingAs($this->vendor(), 'sanctum')
            ->postJson('/api/v1/stripe/connect/onboarding', ['country' => 'FR'])
            ->assertStatus(503);
    }

    public function test_approved_vendor_can_reopen_stripe_page_to_change_iban(): void
    {
        $this->mockStripe(function ($mock) {
            $mock->shouldReceive('accountState')->andReturn($this->state(['ready' => true, 'details_submitted' => true]));
            $mock->shouldReceive('createOnboardingLink')->once()->andReturn(['url' => 'https://connect.stripe.com/c', 'expires_at' => null]);
        });

        $user = $this->vendor(['stripe_account_id' => 'acct_ok', 'stripe_account_status' => 'approved']);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/stripe/connect/onboarding')
            ->assertOk();

        // Reste validé tant que l'IBAN ne change pas (la synchro le repasse sinon en attente).
        $this->assertSame('approved', $user->fresh()->stripe_account_status);
    }

    public function test_rejected_vendor_can_reopen_stripe_page(): void
    {
        $this->mockStripe(function ($mock) {
            $mock->shouldReceive('accountState')->andReturn($this->state(['details_submitted' => true]));
            $mock->shouldReceive('createOnboardingLink')->once()->andReturn(['url' => 'https://connect.stripe.com/r', 'expires_at' => null]);
        });

        $user = $this->vendor([
            'stripe_account_id' => 'acct_r',
            'stripe_account_status' => 'rejected',
            'stripe_rejection_reason' => 'Pièce illisible',
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/stripe/connect/onboarding')
            ->assertOk();

        $this->assertNull($user->fresh()->stripe_account_status);
        $this->assertNull($user->fresh()->stripe_rejection_reason);
    }

    public function test_onboarding_recreates_account_missing_for_current_key(): void
    {
        $this->mockStripe(function ($mock) {
            $mock->shouldReceive('accountState')->with('acct_old_test')
                ->andReturn($this->state(['exists' => false, 'missing' => true]));
            $mock->shouldReceive('createHostedAccount')->once()->andReturn('acct_new_live');
        });

        $user = $this->vendor(['stripe_account_id' => 'acct_old_test', 'stripe_account_status' => 'approved']);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/stripe/connect/onboarding', ['country' => 'FR'])
            ->assertOk();

        $this->assertSame('acct_new_live', $user->fresh()->stripe_account_id);
        $this->assertNull($user->fresh()->stripe_account_status);
    }

    /** Les anciennes versions de l'app reçoivent un message clair, rien n'est envoyé à Stripe. */
    public function test_legacy_iban_form_asks_to_update_the_app(): void
    {
        $this->mockStripe(function ($mock) {
            $mock->shouldReceive('createHostedAccount')->never();
        });

        $this->actingAs($this->vendor(), 'sanctum')
            ->postJson('/api/v1/stripe/connect/submit', ['country' => 'FR', 'iban' => 'FR7630006000011234567890189'])
            ->assertStatus(422)
            ->assertJsonPath('message', __('payments.stripe_connect.hosted_onboarding_required'))
            ->assertJsonPath('data.onboarding_required', true);
    }

    public function test_status_moves_to_pending_once_stripe_registration_is_complete(): void
    {
        $this->mockStripe(function ($mock) {
            $mock->shouldReceive('accountState')->andReturn($this->state([
                'details_submitted' => true,
                'transfers' => 'pending',
            ]));
            $mock->shouldReceive('bankAccountSummary')->with('acct_x')
                ->andReturn(['last4' => '9618', 'country' => 'FR', 'holder' => 'Jean Dupont']);
        });

        $user = $this->vendor(['stripe_account_id' => 'acct_x']);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/stripe/connect/status')
            ->assertOk()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.iban_last4', '9618')
            ->assertJsonPath('data.account_holder_name', 'Jean Dupont')
            ->assertJsonPath('data.stripe.details_submitted', true)
            ->assertJsonPath('data.stripe.verification', __('payments.stripe_connect.pending'));

        $this->assertNotNull($user->fresh()->stripe_submitted_at);
    }

    public function test_status_keeps_unfinished_registration_out_of_review(): void
    {
        $this->mockStripe(function ($mock) {
            $mock->shouldReceive('accountState')->andReturn($this->state(['requirements_due' => ['individual.dob.day']]));
            $mock->shouldReceive('bankAccountSummary')->never();
        });

        $this->actingAs($this->vendor(['stripe_account_id' => 'acct_x']), 'sanctum')
            ->getJson('/api/v1/stripe/connect/status')
            ->assertOk()
            ->assertJsonPath('data.status', null)
            ->assertJsonPath('data.has_account', true)
            ->assertJsonPath('data.stripe.details_submitted', false)
            ->assertJsonPath('data.stripe.verification', __('payments.stripe_connect.onboarding_incomplete'));
    }

    /** Nouvel IBAN saisi chez Stripe sur un compte validé : il doit être revalidé. */
    public function test_iban_changed_on_stripe_sends_approved_account_back_to_review(): void
    {
        $this->mockStripe(function ($mock) {
            $mock->shouldReceive('accountState')->andReturn($this->state(['details_submitted' => true, 'ready' => true, 'transfers' => 'active']));
            $mock->shouldReceive('bankAccountSummary')->andReturn(['last4' => '1111', 'country' => 'FR', 'holder' => 'Jean Dupont']);
        });

        $user = $this->vendor([
            'stripe_account_id' => 'acct_ok',
            'stripe_account_status' => 'approved',
            'stripe_external_last4' => '9618',
            'stripe_verified_at' => now(),
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/stripe/connect/status')
            ->assertOk()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.iban_last4', '1111');

        $this->assertNull($user->fresh()->stripe_verified_at);
    }

    public function test_status_forgets_account_missing_for_current_key(): void
    {
        $this->mockStripe(function ($mock) {
            $mock->shouldReceive('accountState')->andReturn($this->state(['exists' => false, 'missing' => true]));
        });

        $user = $this->vendor(['stripe_account_id' => 'acct_old', 'stripe_account_status' => 'approved', 'stripe_external_last4' => '3000']);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/stripe/connect/status')
            ->assertOk()
            ->assertJsonPath('data.has_account', false)
            ->assertJsonPath('data.status', null);
    }

    public function test_unlink_closes_stripe_account_and_resets_vendor(): void
    {
        $this->mockStripe(function ($mock) {
            $mock->shouldReceive('accountState')->andReturn($this->state(['details_submitted' => true, 'ready' => true]));
            $mock->shouldReceive('connectedHasFunds')->with('acct_existing')->andReturn(false);
            $mock->shouldReceive('closeAccount')->once()->with('acct_existing');
        });

        $user = $this->vendor([
            'stripe_account_id' => 'acct_existing',
            'stripe_account_status' => 'approved',
            'stripe_external_last4' => '2606',
        ]);

        $this->actingAs($user, 'sanctum')
            ->deleteJson('/api/v1/stripe/connect')
            ->assertOk()
            ->assertJsonPath('data.status', null)
            ->assertJsonPath('data.iban_last4', null)
            ->assertJsonPath('data.has_account', false);

        $this->assertFalse(\App\Services\StripeWithdrawalService::isReady($user->fresh()));
    }

    public function test_unlink_refused_while_funds_remain_on_stripe_account(): void
    {
        $this->mockStripe(function ($mock) {
            $mock->shouldReceive('accountState')->andReturn($this->state(['details_submitted' => true]));
            $mock->shouldReceive('connectedHasFunds')->andReturn(true);
            $mock->shouldReceive('closeAccount')->never();
        });

        $user = $this->vendor(['stripe_account_id' => 'acct_existing', 'stripe_account_status' => 'approved']);

        $this->actingAs($user, 'sanctum')
            ->deleteJson('/api/v1/stripe/connect')
            ->assertStatus(422)
            ->assertJsonPath('message', __('payments.stripe_connect.unlink_balance_remaining'));

        $this->assertSame('acct_existing', $user->fresh()->stripe_account_id);
    }

    public function test_unlink_refused_while_a_transfer_is_in_progress(): void
    {
        $user = $this->vendor([
            'stripe_account_id' => 'acct_existing',
            'stripe_account_status' => 'approved',
            'stripe_external_last4' => '2606',
        ]);
        \App\Models\PlatformWithdrawal::create([
            'user_id' => $user->id,
            'amount_requested' => 20,
            'commission_rate' => 0,
            'commission_amount' => 0,
            'amount_sent' => 20,
            'currency' => 'EUR',
            'provider' => 'stripe',
            'payment_method' => 'stripe_connect',
            'payment_account' => 'IBAN ****2606',
            'payment_account_name' => 'Jean Dupont',
            'status' => 'processing',
            'transaction_reference' => 'TXN-' . uniqid(),
        ]);

        $this->actingAs($user, 'sanctum')
            ->deleteJson('/api/v1/stripe/connect')
            ->assertStatus(422);
        $this->assertSame('approved', $user->fresh()->stripe_account_status);
    }

    public function test_return_page_tells_vendor_to_go_back_to_the_app(): void
    {
        $this->get('/stripe/connect/return')
            ->assertOk()
            ->assertSee(__('payments.stripe_connect.return_page.title'));
    }

    public function test_refresh_page_redirects_to_a_new_stripe_link(): void
    {
        $this->mockStripe(function ($mock) {
            $mock->shouldReceive('createOnboardingLink')->once()
                ->andReturn(['url' => 'https://connect.stripe.com/again', 'expires_at' => null]);
        });

        $user = $this->vendor(['stripe_account_id' => 'acct_x']);
        $url = URL::temporarySignedRoute('stripe.connect.refresh', now()->addDay(), ['user' => $user->id]);

        $this->get($url)->assertRedirect('https://connect.stripe.com/again');
    }

    public function test_refresh_page_requires_a_valid_signature(): void
    {
        $user = $this->vendor(['stripe_account_id' => 'acct_x']);

        $this->get('/stripe/connect/refresh/' . $user->id)->assertForbidden();
    }
}
