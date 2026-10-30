<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Aligne la fiche vendeur ASSO sur son compte Stripe Connect.
 *
 * Avec l'inscription hébergée, c'est Stripe qui détient l'identité et l'IBAN : on
 * relit donc Stripe (statut de la page d'inscription, IBAN enregistré, activation)
 * et on reporte ce qui nous concerne sur l'utilisateur.
 *
 * Cycle du statut interne `stripe_account_status` :
 *   null      → inscription Stripe pas encore terminée
 *   pending   → inscription terminée, en attente de validation par un admin ASSO
 *   approved  → validé par ASSO (les virements sont possibles si Stripe est prêt)
 *   rejected  → refusé par ASSO, motif dans `stripe_rejection_reason`
 */
class StripeConnectSync
{
    public function __construct(private StripeService $stripe)
    {
    }

    /**
     * @param bool $fromWebhook true = événement Stripe : un compte validé que Stripe
     *                          désactive repasse en attente (sinon chaque virement
     *                          échouerait un par un).
     * @return array|null État Stripe (voir StripeService::accountState), enrichi de
     *                    `demoted` et `bank_changed` ; null sans compte ni clés.
     */
    public function sync(User $user, bool $fromWebhook = false): ?array
    {
        if (empty($user->stripe_account_id) || !$this->stripe->isConfigured()) {
            return null;
        }

        $state = $this->stripe->accountState($user->stripe_account_id);
        $state['demoted'] = false;
        $state['bank_changed'] = false;

        // Compte introuvable ou Stripe injoignable : rien de fiable à reporter.
        if (!($state['exists'] ?? false)) {
            return $state;
        }

        $updates = [];
        $status = $user->stripe_account_status;

        if ($state['details_submitted'] ?? false) {
            // Inscription terminée chez Stripe : le dossier passe en revue chez ASSO.
            if ($status === null) {
                $status = 'pending';
                $updates += [
                    'stripe_account_status' => 'pending',
                    'stripe_rejection_reason' => null,
                    'stripe_submitted_at' => now(),
                ];
            }

            $bank = $this->stripe->bankAccountSummary($user->stripe_account_id);
            if ($bank !== null) {
                $previous = $user->stripe_external_last4;

                // L'IBAN peut être changé par le vendeur depuis son espace Stripe : un
                // nouvel IBAN sur un compte déjà validé doit être revalidé par ASSO.
                if ($status === 'approved' && !empty($previous) && $bank['last4'] !== $previous) {
                    $status = 'pending';
                    $state['bank_changed'] = true;
                    $updates += [
                        'stripe_account_status' => 'pending',
                        'stripe_verified_at' => null,
                        'stripe_rejection_reason' => null,
                        'stripe_submitted_at' => now(),
                    ];
                }

                $updates += array_filter([
                    'stripe_external_last4' => $bank['last4'],
                    'stripe_bank_country' => $bank['country'],
                    'stripe_account_holder_name' => $bank['holder'],
                ], static fn ($value) => $value !== null);
            }
        }

        if ($fromWebhook && !($state['ready'] ?? false) && $status === 'approved') {
            $due = $state['requirements_due'] ?? [];
            $state['demoted'] = true;
            $updates += [
                'stripe_account_status' => 'pending',
                'stripe_verified_at' => null,
                'stripe_rejection_reason' => 'Informations complémentaires demandées par Stripe'
                    . (empty($due) ? '.' : ' : ' . implode(', ', array_slice($due, 0, 6)) . '.'),
            ];
        }

        // Versement manuel indispensable au rapprochement des virements.
        $interval = $state['payout_interval'] ?? null;
        if ($interval !== null && $interval !== 'manual') {
            $this->stripe->ensureManualPayouts($user->stripe_account_id);
        }

        $updates = array_filter(
            $updates,
            fn ($value, $key) => $user->getAttribute($key) != $value || $key === 'stripe_submitted_at',
            ARRAY_FILTER_USE_BOTH,
        );

        if ($updates !== []) {
            $user->update($updates);

            Log::info('[StripeConnectSync] Fiche vendeur synchronisée avec Stripe', [
                'user_id' => $user->id,
                'fields' => array_keys($updates),
                'status' => $user->stripe_account_status,
            ]);
        }

        return $state;
    }
}
