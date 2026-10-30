<?php

namespace App\Services;

use App\Exceptions\StripePayoutUnavailableException;
use App\Exceptions\StripeWithdrawalException;
use App\Models\PlatformWithdrawal;
use App\Models\Setting;
use App\Models\User;
use App\Models\WalletBalance;
use App\Models\WalletTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Règles communes des retraits Wallet.
 *
 * Origine des fonds : ce qui a été encaissé par carte est chez Stripe, ce qui a été
 * encaissé par Mobile Money est chez KPay / ElgioPay. Un retrait part donc
 * automatiquement quand il reste sur son rail (carte → IBAN Stripe, Mobile Money →
 * numéro Mobile Money). Sinon (carte → Mobile Money, Mobile Money → IBAN, IBAN non
 * pris en charge par Stripe) c'est une DEMANDE : le solde est débité tout de suite
 * et ASSO règle le versement à la main, en ≈ 48 h.
 *
 * Frais : réglables par méthode (pourcentage + fixe en FCFA), retenus sur le montant
 * demandé ; le vendeur reçoit le net. Ils reviennent à ASSO une fois le versement réglé.
 */
class WithdrawalPolicy
{
    /** Devises sans centimes : frais arrondis à l'unité supérieure. */
    private const ZERO_DECIMAL = ['XAF', 'XOF', 'CDF', 'GNF', 'RWF', 'BIF', 'KMF', 'DJF', 'MGA'];

    public function __construct(private FirebaseMessagingService $fcm)
    {
    }

    // ------------------------------------------------------------------
    // Réglages
    // ------------------------------------------------------------------

    public static function manualDelayHours(): int
    {
        $hours = (int) Setting::get('withdrawal_manual_delay_hours', 48);

        return $hours > 0 ? $hours : 48;
    }

    /** @return array<string, array{percent:float, fixed:float, fixed_currency:string}> */
    public static function feeConfig(): array
    {
        return [
            PlatformWithdrawal::METHOD_MOBILE_MONEY => [
                'percent' => max(0.0, (float) Setting::get('withdrawal_fee_mobile_percent', 0)),
                'fixed' => max(0.0, (float) Setting::get('withdrawal_fee_mobile_fixed', 0)),
                'fixed_currency' => 'XAF',
            ],
            PlatformWithdrawal::METHOD_BANK_TRANSFER => [
                'percent' => max(0.0, (float) Setting::get('withdrawal_fee_bank_percent', 0)),
                'fixed' => max(0.0, (float) Setting::get('withdrawal_fee_bank_fixed', 0)),
                'fixed_currency' => 'XAF',
            ],
        ];
    }

    /** L'administrateur agit au nom d'un compte (retrait ASSO, partenaire) : ni frais ni demande. */
    public static function adminActing(Request $request): bool
    {
        return (bool) $request->attributes->get('withdrawal_admin_acting', false);
    }

    // ------------------------------------------------------------------
    // Calculs
    // ------------------------------------------------------------------

    /** Frais d'un retrait de `$amount` (devise du portefeuille `$currency`). */
    public function fee(string $method, float $amount, string $currency): float
    {
        $config = self::feeConfig()[$method] ?? null;
        if (!$config || $amount <= 0) {
            return 0.0;
        }

        $currency = strtoupper($currency);
        $fee = $amount * $config['percent'] / 100;

        if ($config['fixed'] > 0) {
            if ($currency === $config['fixed_currency']) {
                $fee += $config['fixed'];
            } else {
                $converted = ExchangeRateService::convert($config['fixed_currency'], $currency, $config['fixed']);
                if ($converted['success'] ?? false) {
                    $fee += (float) $converted['amount'];
                } else {
                    Log::warning('[WithdrawalPolicy] Frais fixes non convertis', ['to' => $currency]);
                }
            }
        }

        $fee = in_array($currency, self::ZERO_DECIMAL, true) ? ceil($fee) : round($fee, 2);

        return min($fee, $amount);
    }

    /** @return array{currency:string, available:float, card:float, mobile_money:float} */
    public function funds(User $user, string $currency): array
    {
        $currency = strtoupper($currency);
        $row = WalletBalance::where('user_id', $user->id)->where('currency', $currency)->first();

        return [
            'currency' => $currency,
            'available' => round(max(0.0, $row?->available ?? 0.0), 2),
            'card' => round($row?->card_available ?? 0.0, 2),
            'mobile_money' => round($row?->mobile_available ?? 0.0, 2),
        ];
    }

    /** Répartition carte / Mobile Money de chaque solde du portefeuille. */
    public function fundsSummary(User $user): array
    {
        return $user->walletBalances()->get()
            ->map(fn (WalletBalance $row) => [
                'currency' => strtoupper($row->currency),
                'available' => round(max(0.0, $row->available), 2),
                'card' => round($row->card_available, 2),
                'mobile_money' => round($row->mobile_available, 2),
            ])
            ->values()
            ->all();
    }

    /**
     * Part du retrait couverte par les fonds encaissés par carte : le virement IBAN
     * les prend en premier, le Mobile Money en dernier.
     */
    public function cardShare(string $method, float $amount, array $funds): float
    {
        return $method === PlatformWithdrawal::METHOD_BANK_TRANSFER
            ? min($amount, $funds['card'])
            : max(0.0, $amount - $funds['mobile_money']);
    }

    public static function originLabel(float $amount, float $cardPart): string
    {
        return match (true) {
            $cardPart <= 0.009 => 'mobile_money',
            $cardPart >= $amount - 0.009 => 'card',
            default => 'mixed',
        };
    }

    /**
     * Mode de traitement : automatique si les fonds restent sur leur rail et que le
     * prestataire est prêt, sinon demande manuelle.
     *
     * @return array{mode:string, reason:?string}
     */
    public function mode(User $user, string $method, float $amount, string $currency, bool $railReady): array
    {
        $funds = $this->funds($user, $currency);

        if (!$railReady) {
            return ['mode' => PlatformWithdrawal::MODE_MANUAL, 'reason' => $method === PlatformWithdrawal::METHOD_BANK_TRANSFER ? 'iban_not_stripe' : 'rail_unavailable'];
        }

        $sameRail = $method === PlatformWithdrawal::METHOD_BANK_TRANSFER
            ? $amount <= $funds['card'] + 0.009
            : $amount <= $funds['mobile_money'] + 0.009;

        if ($sameRail) {
            return ['mode' => PlatformWithdrawal::MODE_AUTOMATIC, 'reason' => null];
        }

        return [
            'mode' => PlatformWithdrawal::MODE_MANUAL,
            'reason' => $method === PlatformWithdrawal::METHOD_BANK_TRANSFER ? 'mobile_funds_to_bank' : 'card_funds_to_mobile',
        ];
    }

    public static function reasonMessage(?string $reason): ?string
    {
        if ($reason === null) {
            return null;
        }

        return __('wallet.withdrawal_policy.reasons.' . $reason, ['hours' => self::manualDelayHours()]);
    }

    // ------------------------------------------------------------------
    // Demande manuelle
    // ------------------------------------------------------------------

    /**
     * Débite le portefeuille et enregistre la demande que ASSO réglera à la main.
     *
     * @param array{payment_method:string, payment_account:string, payment_account_name:?string,
     *              payout_currency?:?string} $destination
     * @param array{notes?:?string, ip?:?string, user_agent?:?string, reason?:?string} $context
     *
     * @throws StripeWithdrawalException message prêt à afficher
     */
    public function createManualRequest(User $user, string $method, float $amount, string $currency, array $destination, array $context = []): PlatformWithdrawal
    {
        $currency = strtoupper($currency);
        $amount = in_array($currency, self::ZERO_DECIMAL, true) ? round($amount) : round($amount, 2);
        $fee = $this->fee($method, $amount, $currency);
        $net = round($amount - $fee, 2);

        if ($net <= 0) {
            throw new StripeWithdrawalException(__('wallet.withdrawal_policy.amount_below_fee'), 422);
        }

        $withdrawal = DB::transaction(function () use ($user, $method, $amount, $currency, $fee, $net, $destination, $context) {
            $row = WalletBalance::where('user_id', $user->id)->where('currency', $currency)->lockForUpdate()->first();
            $available = $row ? (float) $row->balance - (float) $row->locked_balance : 0.0;

            if ($amount > $available + 0.009) {
                throw new StripeWithdrawalException(__('wallet.insufficient_currency_balance', [
                    'currency' => $currency,
                    'available' => number_format(max(0, $available), 0, ',', ' '),
                ]), 400);
            }

            $funds = [
                'card' => $row->card_available,
                'mobile_money' => $row->mobile_available,
            ];
            $cardPart = round($this->cardShare($method, $amount, $funds), 2);
            $balanceBefore = (float) $row->balance;

            // Le virement IBAN consomme la part carte en premier ; pour le Mobile Money
            // le trigger SQL la réduit d'elle-même une fois la part mobile épuisée.
            if ($method === PlatformWithdrawal::METHOD_BANK_TRANSFER && $cardPart > 0) {
                WalletBalance::useCardFunds($user->id, $currency, $cardPart);
            }

            $tx = WalletTransaction::create([
                'user_id' => $user->id,
                'type' => 'debit',
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceBefore - $amount,
                'description' => WalletTransaction::label(
                    $method === PlatformWithdrawal::METHOD_BANK_TRANSFER ? 'withdrawal_request_bank' : 'withdrawal_request_mobile',
                    ['account' => $destination['payment_account']]
                ),
                'status' => 'pending',
                'provider' => PlatformWithdrawal::PROVIDER_MANUAL,
                'reference_type' => 'platform_withdrawal',
                'reference_id' => null,
                'metadata' => [
                    'currency' => $currency,
                    'method' => $method,
                    'fee' => $fee,
                    'net_amount' => $net,
                    'manual' => true,
                ],
            ]);

            $user->debitKpay($currency, $amount);

            $withdrawal = PlatformWithdrawal::create([
                'user_id' => $user->id,
                'amount_requested' => $amount,
                'commission_rate' => self::feeConfig()[$method]['percent'] ?? 0,
                'commission_amount' => $fee,
                'amount_sent' => $net,
                'currency' => $currency,
                'payout_currency' => $destination['payout_currency'] ?? null,
                'provider' => PlatformWithdrawal::PROVIDER_MANUAL,
                'payment_method' => $destination['payment_method'],
                'payment_account' => $destination['payment_account'],
                'payment_account_name' => $destination['payment_account_name'] ?? $user->name,
                'status' => 'pending',
                'method' => $method,
                'processing_mode' => PlatformWithdrawal::MODE_MANUAL,
                'funds_origin' => self::originLabel($amount, $cardPart),
                'card_funds_used' => $cardPart,
                'expected_by' => now()->addHours(self::manualDelayHours()),
                'failure_code' => $context['reason'] ?? null,
                'transaction_reference' => 'WRQ-' . now()->format('YmdHis') . '-' . strtoupper(Str::random(4)),
                'admin_notes' => $context['notes'] ?? null,
                'ip_address' => $context['ip'] ?? null,
                'user_agent' => $context['user_agent'] ?? null,
            ]);

            $tx->update(['reference_id' => $withdrawal->id]);

            return $withdrawal;
        });

        Log::info('[WithdrawalPolicy] Demande de retrait manuelle enregistrée', [
            'withdrawal_id' => $withdrawal->id,
            'user_id' => $user->id,
            'method' => $method,
            'amount' => $amount,
            'fee' => $fee,
            'origin' => $withdrawal->funds_origin,
        ]);

        $this->notify($user, 'wallet_withdrawal_requested', [
            'amount' => $this->money($net, $currency),
            'hours' => self::manualDelayHours(),
        ], $withdrawal, 'wallet_withdrawal_requested');

        return $withdrawal;
    }

    // ------------------------------------------------------------------
    // Traitement (vendeur / admin)
    // ------------------------------------------------------------------

    /** Le vendeur annule sa demande tant qu'ASSO ne l'a pas prise en charge. */
    public function cancelByUser(PlatformWithdrawal $withdrawal): bool
    {
        if (!$withdrawal->isManual() || $withdrawal->status !== 'pending' || $withdrawal->provider !== PlatformWithdrawal::PROVIDER_MANUAL) {
            return false;
        }

        return $this->refund($withdrawal, 'cancelled', 'cancelled_by_user', __('wallet.withdrawal_policy.cancelled_by_user'));
    }

    public function markProcessing(PlatformWithdrawal $withdrawal, User $admin): void
    {
        if ($withdrawal->status !== 'pending') {
            return;
        }

        $withdrawal->update([
            'status' => 'processing',
            'processed_by' => $admin->id,
            'processed_at' => now(),
        ]);
    }

    /** Versement fait hors application (virement, transfert Mobile Money) : on clôt. */
    public function complete(PlatformWithdrawal $withdrawal, User $admin, ?string $reference = null, ?string $note = null): bool
    {
        $done = DB::transaction(function () use ($withdrawal, $admin, $reference, $note) {
            $locked = PlatformWithdrawal::whereKey($withdrawal->id)->lockForUpdate()->first();
            if (!$locked || !in_array($locked->status, ['pending', 'processing'], true) || $locked->provider !== PlatformWithdrawal::PROVIDER_MANUAL) {
                return false;
            }

            $locked->update([
                'status' => 'completed',
                'completed_at' => now(),
                'processed_by' => $admin->id,
                'processed_at' => $locked->processed_at ?? now(),
                'payout_reference' => $reference,
                'admin_notes' => $this->appendNote($locked->admin_notes, $note),
            ]);

            WalletTransaction::where('reference_type', 'platform_withdrawal')
                ->where('reference_id', $locked->id)
                ->where('type', 'debit')
                ->update(['status' => 'completed']);

            return true;
        });

        if ($done) {
            $withdrawal->refresh();
            $this->notify($withdrawal->user, 'wallet_withdrawal_request_completed', [
                'amount' => $this->money((float) $withdrawal->amount_sent, $withdrawal->currency),
                'account' => $withdrawal->payment_account,
            ], $withdrawal, 'wallet_withdrawal_completed');
        }

        return $done;
    }

    /** Demande refusée : le portefeuille est recrédité du montant débité. */
    public function reject(PlatformWithdrawal $withdrawal, User $admin, string $reason): bool
    {
        $done = $this->refund($withdrawal, 'failed', 'rejected_by_admin', $reason, $admin);

        if ($done) {
            $withdrawal->refresh();
            $this->notify($withdrawal->user, 'wallet_withdrawal_rejected', [
                'amount' => $this->money((float) $withdrawal->amount_requested, $withdrawal->currency),
                'reason' => $reason,
            ], $withdrawal, 'wallet_withdrawal_failed');
        }

        return $done;
    }

    /**
     * L'admin envoie la demande par le prestataire (fonds disponibles côté ASSO) :
     * le retrait devient un retrait automatique ordinaire, suivi par les jobs existants.
     *
     * @throws StripeWithdrawalException
     */
    public function executeAutomatically(PlatformWithdrawal $withdrawal, User $admin): PlatformWithdrawal
    {
        $withdrawal->refresh();
        if ($withdrawal->provider !== PlatformWithdrawal::PROVIDER_MANUAL || !in_array($withdrawal->status, ['pending', 'processing'], true)) {
            throw new StripeWithdrawalException('Cette demande n’est plus en attente.', 409);
        }

        $user = $withdrawal->user;
        if (!$user) {
            throw new StripeWithdrawalException('Utilisateur introuvable.', 404);
        }

        return $withdrawal->method === PlatformWithdrawal::METHOD_MOBILE_MONEY
            ? $this->executeMobile($withdrawal, $user, $admin)
            : $this->executeStripe($withdrawal, $user, $admin);
    }

    private function executeMobile(PlatformWithdrawal $withdrawal, User $user, User $admin): PlatformWithdrawal
    {
        $gateway = app(MobileMoneyGateway::class);
        if (!$gateway->isConfigured()) {
            throw new StripeWithdrawalException('Aucun prestataire Mobile Money n’est configuré.', 503);
        }

        $result = $gateway->initiateDisbursement([
            'amount' => (float) $withdrawal->amount_sent,
            'provider' => $withdrawal->payment_method,
            'phone_number' => $withdrawal->payment_account,
            'description' => "Retrait wallet #{$withdrawal->id}",
            'external_reference' => "WITHDRAW-{$withdrawal->id}",
            'recipient_name' => $withdrawal->payment_account_name ?? $user->name,
        ]);

        if (!($result['success'] ?? false)) {
            throw new StripeWithdrawalException('Envoi Mobile Money refusé : ' . ($result['message'] ?? 'erreur inconnue'), 502);
        }

        $withdrawal->update([
            'provider' => 'kpay',
            'kpay_reference' => $result['id'] ?? null,
            'kpay_response' => $result['data'] ?? [],
            'status' => 'processing',
            'processed_by' => $admin->id,
            'processed_at' => now(),
        ]);
        WalletTransaction::where('reference_type', 'platform_withdrawal')->where('reference_id', $withdrawal->id)
            ->where('type', 'debit')->update(['provider' => 'kpay']);

        try {
            foreach ([30, 120, 600] as $delay) {
                \App\Jobs\Wallet\ProcessWithdrawalStatusJob::dispatch($withdrawal->id)
                    ->onQueue('withdrawals')->delay(now()->addSeconds($delay));
            }
        } catch (\Throwable $e) {
            Log::warning('[WithdrawalPolicy] Suivi du retrait non planifié : ' . $e->getMessage());
        }

        return $withdrawal->fresh();
    }

    private function executeStripe(PlatformWithdrawal $withdrawal, User $user, User $admin): PlatformWithdrawal
    {
        if (!StripeWithdrawalService::isReady($user)) {
            throw new StripeWithdrawalException('L’IBAN de ce vendeur n’est pas validé chez Stripe : faites le virement à la main puis marquez la demande payée.', 422);
        }

        $stripe = app(StripeService::class);
        $payoutCurrency = app(StripeWithdrawalService::class)->payoutCurrency($user->stripe_bank_country);
        $source = strtoupper((string) $withdrawal->currency);

        if ($source === $payoutCurrency) {
            $rate = 1.0;
        } else {
            $conversion = ExchangeRateService::convert($source, $payoutCurrency, 1);
            if (!($conversion['success'] ?? false)) {
                throw new StripeWithdrawalException("Taux {$source} → {$payoutCurrency} indisponible.", 503);
            }
            $rate = (float) $conversion['rate'];
        }

        $payoutAmount = round((float) $withdrawal->amount_sent * $rate, 2);

        try {
            $stripe->assertPayoutPossible($user->stripe_account_id, $payoutAmount, $payoutCurrency);
            $result = $stripe->payoutToVendor($user->stripe_account_id, $payoutAmount, $payoutCurrency);
        } catch (StripePayoutUnavailableException $e) {
            throw new StripeWithdrawalException('Stripe refuse le virement : ' . $e->getMessage(), 503);
        }

        $withdrawal->update([
            'provider' => 'stripe',
            'payment_method' => 'stripe_connect',
            'payout_amount' => (float) ($result['payout_amount'] ?? $payoutAmount),
            'payout_currency' => $payoutCurrency,
            'exchange_rate' => $rate,
            'stripe_transfer_id' => $result['transfer_id'] ?? null,
            'stripe_payout_id' => $result['payout_id'] ?? null,
            'stripe_response' => $result,
            'status' => 'processing',
            'processed_by' => $admin->id,
            'processed_at' => now(),
        ]);
        WalletTransaction::where('reference_type', 'platform_withdrawal')->where('reference_id', $withdrawal->id)
            ->where('type', 'debit')->update(['provider' => 'stripe']);

        return $withdrawal->fresh();
    }

    /**
     * Recrédite le portefeuille (demande annulée / refusée). Idempotent.
     */
    private function refund(PlatformWithdrawal $withdrawal, string $status, string $code, string $reason, ?User $admin = null): bool
    {
        return DB::transaction(function () use ($withdrawal, $status, $code, $reason, $admin) {
            $locked = PlatformWithdrawal::whereKey($withdrawal->id)->lockForUpdate()->first();
            if (!$locked || !in_array($locked->status, ['pending', 'processing'], true) || $locked->provider !== PlatformWithdrawal::PROVIDER_MANUAL) {
                return false;
            }

            $user = $locked->user;
            if ($user) {
                $currency = strtoupper((string) $locked->currency);
                $amount = (float) $locked->amount_requested;
                $before = $user->kpayBalanceFor($currency);

                $user->creditKpay($currency, $amount);
                self::restoreCardFunds($locked);

                WalletTransaction::create([
                    'user_id' => $user->id,
                    'type' => 'credit',
                    'amount' => $amount,
                    'balance_before' => $before,
                    'balance_after' => $before + $amount,
                    'description' => WalletTransaction::label(
                        $status === 'cancelled' ? 'withdrawal_request_cancelled' : 'withdrawal_request_rejected',
                        ['reference' => $locked->transaction_reference]
                    ),
                    'status' => 'completed',
                    'provider' => PlatformWithdrawal::PROVIDER_MANUAL,
                    'reference_type' => 'platform_withdrawal_refund',
                    'reference_id' => $locked->id,
                    'metadata' => ['refund' => true, 'currency' => $currency, 'reason' => $code],
                ]);
            }

            WalletTransaction::where('reference_type', 'platform_withdrawal')
                ->where('reference_id', $locked->id)
                ->where('type', 'debit')
                ->update(['status' => 'failed']);

            $locked->update([
                'status' => $status,
                'failure_code' => $code,
                'failure_reason' => $reason,
                'processed_by' => $admin?->id ?? $locked->processed_by,
                'processed_at' => $admin ? now() : $locked->processed_at,
            ]);

            return true;
        });
    }

    /** Rend au solde « carte » la part consommée par un retrait remboursé. */
    public static function restoreCardFunds(PlatformWithdrawal $withdrawal): void
    {
        $used = (float) ($withdrawal->card_funds_used ?? 0);
        if ($used > 0 && $withdrawal->user_id) {
            WalletBalance::addCardFunds($withdrawal->user_id, (string) $withdrawal->currency, $used);
        }
    }

    /**
     * Frais acquis : crédités au compte ASSO une seule fois, quand le versement est réglé.
     */
    public function creditFee(PlatformWithdrawal $withdrawal): void
    {
        $fee = (float) $withdrawal->commission_amount;
        if ($fee <= 0 || $withdrawal->fee_credited_at !== null) {
            return;
        }

        $platform = CommissionService::platformAccount();
        if (!$platform || $platform->id === $withdrawal->user_id) {
            return;
        }

        try {
            DB::transaction(function () use ($withdrawal, $platform, $fee) {
                $claimed = PlatformWithdrawal::whereKey($withdrawal->id)
                    ->whereNull('fee_credited_at')
                    ->update(['fee_credited_at' => now()]);
                if ($claimed === 0) {
                    return; // déjà crédité par un autre chemin
                }

                $currency = strtoupper((string) $withdrawal->currency);
                $before = $platform->kpayBalanceFor($currency);
                $platform->creditKpay($currency, $fee);

                WalletTransaction::create([
                    'user_id' => $platform->id,
                    'type' => 'credit',
                    'amount' => $fee,
                    'balance_before' => $before,
                    'balance_after' => $before + $fee,
                    'description' => WalletTransaction::label('withdrawal_fee', ['reference' => $withdrawal->transaction_reference]),
                    'status' => 'completed',
                    'provider' => 'kpay',
                    'reference_type' => 'withdrawal_fee',
                    'reference_id' => $withdrawal->id,
                    'metadata' => ['currency' => $currency, 'method' => $withdrawal->method, 'withdrawal_user_id' => $withdrawal->user_id],
                ]);
            });
        } catch (\Throwable $e) {
            Log::error('[WithdrawalPolicy] Frais de retrait non crédités : ' . $e->getMessage(), ['withdrawal_id' => $withdrawal->id]);
        }
    }

    // ------------------------------------------------------------------
    // Divers
    // ------------------------------------------------------------------

    public function money(float $amount, string $currency): string
    {
        $currency = strtoupper($currency);
        $decimals = in_array($currency, self::ZERO_DECIMAL, true) ? 0 : 2;
        $label = in_array($currency, ['XAF', 'XOF'], true) ? 'FCFA' : $currency;

        return number_format($amount, $decimals, ',', ' ') . ' ' . $label;
    }

    private function appendNote(?string $existing, ?string $note): ?string
    {
        $note = trim((string) $note);
        if ($note === '') {
            return $existing;
        }

        return mb_substr(trim(($existing ? $existing . "\n" : '') . $note), 0, 2000);
    }

    private function notify(?User $user, string $key, array $replace, PlatformWithdrawal $withdrawal, string $type): void
    {
        if (!$user) {
            return;
        }

        try {
            $this->fcm->sendToUser(
                $user,
                $user->localized("notifications.{$key}.title"),
                $user->localized("notifications.{$key}.body", $replace),
                [
                    'type' => $type,
                    'withdrawal_id' => $withdrawal->id,
                    'transaction_reference' => $withdrawal->transaction_reference,
                    'method' => $withdrawal->method,
                    'processing_mode' => $withdrawal->processing_mode,
                    'amount' => (string) $withdrawal->amount_sent,
                    'currency' => $withdrawal->currency,
                    'status' => $withdrawal->status,
                ]
            );
        } catch (\Throwable $e) {
            Log::warning('[WithdrawalPolicy] Notification non envoyée : ' . $e->getMessage());
        }
    }
}
