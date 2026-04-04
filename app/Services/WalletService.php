<?php

namespace App\Services;

use App\Models\User;
use App\Models\WalletTransaction;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WalletService
{
    /**
     * Recharge le wallet d'un utilisateur
     *
     * @param User $user
     * @param float $amount
     * @param Transaction|null $transaction Transaction source (FreeMoPay, PayPal, etc.)
     * @param string $description
     * @param array $metadata
     * @param string $provider Provider (freemopay ou paypal)
     * @return WalletTransaction
     */
    public function credit(
        User $user,
        float $amount,
        ?Transaction $transaction = null,
        string $description = 'Recharge wallet',
        array $metadata = [],
        string $provider = 'freemopay'
    ): WalletTransaction {
        return DB::transaction(function () use ($user, $amount, $transaction, $description, $metadata, $provider) {
            // Déterminer quel wallet mettre à jour
            $walletField = $provider === 'paypal' ? 'paypal_wallet_balance' : 'freemopay_wallet_balance';

            $balanceBefore = $user->{$walletField} ?? 0;
            $balanceAfter = $balanceBefore + $amount;

            // Mettre à jour le solde du wallet spécifique
            $user->{$walletField} = $balanceAfter;
            $user->save();

            // Créer la transaction
            $walletTransaction = WalletTransaction::create([
                'user_id' => $user->id,
                'type' => 'credit',
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'description' => $description,
                'payment_id' => $transaction?->id,
                'metadata' => $metadata,
                'status' => 'completed',
                'provider' => $provider,
            ]);

            Log::info("[WalletService] Wallet credited", [
                'user_id' => $user->id,
                'provider' => $provider,
                'amount' => $amount,
                'balance_after' => $balanceAfter,
                'transaction_id' => $walletTransaction->id,
            ]);

            return $walletTransaction;
        });
    }

    /**
     * Débite le wallet d'un utilisateur
     *
     * @param User $user
     * @param float $amount
     * @param string $description
     * @param string|null $referenceType Type de référence (order, subscription, etc.)
     * @param int|null $referenceId ID de la référence
     * @param array $metadata
     * @param string $provider Provider OBLIGATOIRE (freemopay ou paypal)
     * @return WalletTransaction
     * @throws \Exception Si solde insuffisant ou provider invalide
     */
    public function debit(
        User $user,
        float $amount,
        string $description,
        ?string $referenceType = null,
        ?int $referenceId = null,
        array $metadata = [],
        string $provider
    ): WalletTransaction {
        return DB::transaction(function () use ($user, $amount, $description, $referenceType, $referenceId, $metadata, $provider) {
            // Valider le provider
            if (!in_array($provider, ['freemopay', 'paypal'])) {
                throw new \Exception("Provider invalide. Doit être 'freemopay' ou 'paypal'.");
            }

            // Déterminer quel wallet débiter
            $walletField = $provider === 'paypal' ? 'paypal_wallet_balance' : 'freemopay_wallet_balance';

            $balanceBefore = $user->{$walletField} ?? 0;

            // Vérifier le solde
            if ($balanceBefore < $amount) {
                $providerName = $provider === 'paypal' ? 'PayPal' : 'FreeMoPay';
                throw new \Exception("Solde {$providerName} insuffisant. Solde actuel: {$balanceBefore} FCFA, Montant requis: {$amount} FCFA");
            }

            $balanceAfter = $balanceBefore - $amount;

            // Mettre à jour le solde du wallet spécifique
            $user->{$walletField} = $balanceAfter;
            $user->save();

            // Créer la transaction
            $walletTransaction = WalletTransaction::create([
                'user_id' => $user->id,
                'type' => 'debit',
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'description' => $description,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'metadata' => $metadata,
                'status' => 'completed',
                'provider' => $provider,
            ]);

            Log::info("[WalletService] Wallet debited", [
                'user_id' => $user->id,
                'provider' => $provider,
                'amount' => $amount,
                'balance_after' => $balanceAfter,
                'transaction_id' => $walletTransaction->id,
                'reference' => "{$referenceType}:{$referenceId}",
            ]);

            return $walletTransaction;
        });
    }

    /**
     * Rembourse une transaction (remet l'argent dans le wallet)
     *
     * @param WalletTransaction $originalTransaction Transaction à rembourser
     * @param string|null $reason Raison du remboursement
     * @return WalletTransaction
     */
    public function refund(WalletTransaction $originalTransaction, ?string $reason = null): WalletTransaction
    {
        // On ne peut rembourser qu'un débit
        if ($originalTransaction->type !== 'debit') {
            throw new \Exception("Seuls les débits peuvent être remboursés");
        }

        if ($originalTransaction->status !== 'completed') {
            throw new \Exception("Seules les transactions complétées peuvent être remboursées");
        }

        $user = $originalTransaction->user;
        $amount = abs($originalTransaction->amount);
        $description = "Remboursement: " . ($reason ?? $originalTransaction->description);

        return $this->credit(
            $user,
            $amount,
            null,
            $description,
            [
                'refund_of_transaction_id' => $originalTransaction->id,
                'refund_reason' => $reason,
                'original_description' => $originalTransaction->description,
            ],
            $originalTransaction->provider
        );
    }

    /**
     * Ajoute un bonus au wallet (promotion, parrainage, etc.)
     *
     * @param User $user
     * @param float $amount
     * @param string $description
     * @param array $metadata
     * @param string $provider
     * @return WalletTransaction
     */
    public function addBonus(
        User $user,
        float $amount,
        string $description = 'Bonus',
        array $metadata = [],
        string $provider = 'freemopay'
    ): WalletTransaction {
        return DB::transaction(function () use ($user, $amount, $description, $metadata, $provider) {
            $walletField = $provider === 'paypal' ? 'paypal_wallet_balance' : 'freemopay_wallet_balance';

            $balanceBefore = $user->{$walletField} ?? 0;
            $balanceAfter = $balanceBefore + $amount;

            $user->{$walletField} = $balanceAfter;
            $user->save();

            $walletTransaction = WalletTransaction::create([
                'user_id' => $user->id,
                'type' => 'bonus',
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'description' => $description,
                'metadata' => $metadata,
                'status' => 'completed',
                'provider' => $provider,
            ]);

            Log::info("[WalletService] Bonus added", [
                'user_id' => $user->id,
                'amount' => $amount,
                'balance_after' => $balanceAfter,
                'provider' => $provider,
            ]);

            return $walletTransaction;
        });
    }

    /**
     * Ajustement manuel par un admin
     *
     * @param User $user
     * @param float $amount Positif pour ajouter, négatif pour retirer
     * @param User $admin Admin effectuant l'ajustement
     * @param string $reason Raison de l'ajustement
     * @param string $provider
     * @return WalletTransaction
     */
    public function adjustBalance(
        User $user,
        float $amount,
        User $admin,
        string $reason,
        string $provider = 'freemopay'
    ): WalletTransaction {
        return DB::transaction(function () use ($user, $amount, $admin, $reason, $provider) {
            $walletField = $provider === 'paypal' ? 'paypal_wallet_balance' : 'freemopay_wallet_balance';

            $balanceBefore = $user->{$walletField} ?? 0;
            $balanceAfter = $balanceBefore + $amount;

            // Ne pas permettre de balance négative
            if ($balanceAfter < 0) {
                throw new \Exception("L'ajustement rendrait le solde négatif");
            }

            $user->{$walletField} = $balanceAfter;
            $user->save();

            $walletTransaction = WalletTransaction::create([
                'user_id' => $user->id,
                'type' => 'adjustment',
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'description' => "Ajustement admin: {$reason}",
                'admin_id' => $admin->id,
                'metadata' => [
                    'admin_name' => $admin->name,
                    'reason' => $reason,
                ],
                'status' => 'completed',
                'provider' => $provider,
            ]);

            Log::warning("[WalletService] Balance adjusted by admin", [
                'user_id' => $user->id,
                'admin_id' => $admin->id,
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'reason' => $reason,
                'provider' => $provider,
            ]);

            return $walletTransaction;
        });
    }

    /**
     * Récupère l'historique des transactions avec pagination
     *
     * @param User $user
     * @param int $perPage
     * @param string|null $type Filtrer par type
     * @param string|null $provider Filtrer par provider
     * @return \Illuminate\Pagination\LengthAwarePaginator
     */
    public function getTransactionHistory(User $user, int $perPage = 20, ?string $type = null, ?string $provider = null)
    {
        $query = $user->walletTransactions()->with(['payment', 'admin'])->recent();

        if ($type) {
            $query->where('type', $type);
        }

        if ($provider) {
            $query->where('provider', $provider);
        }

        return $query->paginate($perPage);
    }

    /**
     * Statistiques du wallet pour un utilisateur
     *
     * @param User $user
     * @return array
     */
    public function getWalletStats(User $user): array
    {
        $transactions = $user->walletTransactions()->completed();

        // Récupérer les soldes séparés
        $freemopayBalance = $user->freemopay_wallet_balance ?? 0;
        $paypalBalance = $user->paypal_wallet_balance ?? 0;
        $totalBalance = $freemopayBalance + $paypalBalance;

        // Stats par provider
        $freemopayCredits = $transactions->clone()->where('provider', 'freemopay')->credits()->sum('amount');
        $freemopayDebits = abs($transactions->clone()->where('provider', 'freemopay')->debits()->sum('amount'));

        $paypalCredits = $transactions->clone()->where('provider', 'paypal')->credits()->sum('amount');
        $paypalDebits = abs($transactions->clone()->where('provider', 'paypal')->debits()->sum('amount'));

        $totalCredits = $freemopayCredits + $paypalCredits;
        $totalDebits = $freemopayDebits + $paypalDebits;

        return [
            // Soldes par provider
            'freemopay_balance' => $freemopayBalance,
            'paypal_balance' => $paypalBalance,
            'current_balance' => $totalBalance,
            'formatted_balance' => number_format($totalBalance, 0, ',', ' ') . ' FCFA',

            // Totaux
            'total_credits' => $totalCredits,
            'total_debits' => $totalDebits,
            'total_transactions' => $transactions->count(),
            'last_transaction' => $transactions->first(),

            // Par provider
            'freemopay_credits' => $freemopayCredits,
            'freemopay_debits' => $freemopayDebits,
            'paypal_credits' => $paypalCredits,
            'paypal_debits' => $paypalDebits,
        ];
    }

    /**
     * Vérifie si un user peut effectuer un paiement avec son wallet
     *
     * @param User $user
     * @param float $amount
     * @param string|null $provider Provider spécifique (null = total des deux wallets)
     * @return array ['can_pay' => bool, 'message' => string, 'missing_amount' => float]
     */
    public function canPayWithWallet(User $user, float $amount, ?string $provider = null): array
    {
        if ($provider) {
            // Vérifier un wallet spécifique
            $walletField = $provider === 'paypal' ? 'paypal_wallet_balance' : 'freemopay_wallet_balance';
            $balance = $user->{$walletField} ?? 0;
            $providerName = $provider === 'paypal' ? 'PayPal' : 'FreeMoPay';

            $canPay = $balance >= $amount;

            return [
                'can_pay' => $canPay,
                'current_balance' => $balance,
                'required_amount' => $amount,
                'missing_amount' => $canPay ? 0 : ($amount - $balance),
                'provider' => $provider,
                'message' => $canPay
                    ? "Paiement possible avec wallet {$providerName}"
                    : "Solde {$providerName} insuffisant. Il vous manque " . number_format($amount - $balance, 0, ',', ' ') . " FCFA",
            ];
        } else {
            // Vérifier le total des deux wallets
            $freemopayBalance = $user->freemopay_wallet_balance ?? 0;
            $paypalBalance = $user->paypal_wallet_balance ?? 0;
            $totalBalance = $freemopayBalance + $paypalBalance;

            $canPay = $totalBalance >= $amount;

            return [
                'can_pay' => $canPay,
                'freemopay_balance' => $freemopayBalance,
                'paypal_balance' => $paypalBalance,
                'total_balance' => $totalBalance,
                'required_amount' => $amount,
                'missing_amount' => $canPay ? 0 : ($amount - $totalBalance),
                'message' => $canPay
                    ? "Paiement possible"
                    : "Solde total insuffisant. Il vous manque " . number_format($amount - $totalBalance, 0, ',', ' ') . " FCFA",
            ];
        }
    }
}
