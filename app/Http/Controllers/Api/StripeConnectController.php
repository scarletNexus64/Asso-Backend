<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\StripeConnectSync;
use App\Services\StripeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;

/**
 * Compte de virement (IBAN) du VENDEUR via Stripe Connect.
 *
 * L'identité et l'IBAN sont saisis par le vendeur sur la page d'inscription
 * HÉBERGÉE par Stripe : la réglementation française interdit à la plateforme de
 * les collecter elle-même (« you can only create connected accounts using
 * Stripe-hosted onboarding »). L'application demande un lien (onboarding), l'ouvre,
 * puis relit le statut ; une fois l'inscription terminée, le dossier passe
 * `pending` et un admin ASSO le valide (Admin\StripeConnectController).
 */
class StripeConnectController extends Controller
{
    public function __construct(
        private StripeService $stripe,
        private StripeConnectSync $sync,
    ) {
    }

    /**
     * Statut du compte de virement du vendeur connecté, relu chez Stripe.
     * GET /v1/stripe/connect/status
     */
    public function status(Request $request)
    {
        $user = $request->user();
        $stripeBlock = null;

        if (!empty($user->stripe_account_id) && $this->stripe->isConfigured()) {
            $state = $this->sync->sync($user);
            $user->refresh();

            // Compte inexistant pour la clé actuelle (créé en mode test) : on repart
            // de zéro au prochain lien d'inscription.
            if ($state['missing'] ?? false) {
                $this->forgetAccount($user);
            } elseif ($state !== null) {
                $stripeBlock = [
                    'ready' => $state['ready'],
                    'details_submitted' => $state['details_submitted'] ?? false,
                    'verification' => $this->verificationLabel($state),
                    'requirements_due' => $state['requirements_due'],
                    'unavailable' => !($state['exists'] ?? false),
                ];
            }
        }

        $data = $this->formatStatus($user);
        if ($stripeBlock !== null) {
            $data['stripe'] = $stripeBlock;
        }

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Session de l'inscription INTÉGRÉE dans l'application (composant Stripe
     * « account onboarding » des SDK natifs) : le vendeur saisit identité et IBAN
     * dans un écran aux couleurs d'ASSO. Création du compte au premier appel.
     * Rappelée par le SDK quand la session expire.
     * POST /v1/stripe/connect/account-session   { country: "FR" }
     */
    public function accountSession(Request $request)
    {
        if ($unavailable = $this->unavailableResponse()) {
            return $unavailable;
        }
        if ($this->stripe->keysProblem() !== null) {
            Log::critical('[StripeConnectController] Inscription intégrée impossible : ' . $this->stripe->keysProblem());

            return response()->json([
                'success' => false,
                'message' => __('payments.stripe_connect.error_unavailable'),
            ], 503);
        }

        $user = $request->user();

        try {
            if ($error = $this->prepareAccount($request, $user)) {
                return $error;
            }

            $session = $this->stripe->createOnboardingSession($user->stripe_account_id);

            return response()->json([
                'success' => true,
                'data' => [
                    'client_secret' => $session['client_secret'],
                    'expires_at' => $session['expires_at'],
                    'publishable_key' => $this->stripe->publishableKey(),
                    'status' => $this->formatStatus($user->fresh()),
                ],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            return $this->stripeFailure($e, $user, 'Échec de la session d\'inscription Stripe Connect');
        }
    }

    /**
     * Lien vers la page d'inscription hébergée par Stripe (secours si l'écran
     * intégré ne peut pas s'ouvrir sur l'appareil). Création du compte au premier appel.
     * POST /v1/stripe/connect/onboarding   { country: "FR" }
     */
    public function onboarding(Request $request)
    {
        if ($unavailable = $this->unavailableResponse()) {
            return $unavailable;
        }

        $user = $request->user();

        try {
            if ($error = $this->prepareAccount($request, $user)) {
                return $error;
            }

            $link = $this->stripe->createOnboardingLink(
                $user->stripe_account_id,
                $this->refreshUrl($user),
                route('stripe.connect.return'),
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'url' => $link['url'],
                    'expires_at' => $link['expires_at'],
                    'return_url' => route('stripe.connect.return'),
                    'status' => $this->formatStatus($user->fresh()),
                ],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            return $this->stripeFailure($e, $user, 'Échec du lien d\'inscription Stripe Connect');
        }
    }

    private function unavailableResponse()
    {
        if ($this->stripe->isConfigured()) {
            return null;
        }

        return response()->json([
            'success' => false,
            'message' => __('wallet.stripe_withdrawal_unavailable'),
        ], 503);
    }

    /**
     * Garantit un compte Stripe utilisable pour l'inscription : le crée au premier
     * appel (pays obligatoire), le recrée s'il n'existe pas pour la clé actuelle,
     * rouvre un dossier refusé. Renvoie une réponse d'erreur, ou null si tout va bien.
     */
    private function prepareAccount(Request $request, User $user)
    {
        $countryInput = $request->input('country');
        if (is_string($countryInput)) {
            $request->merge(['country' => strtoupper(trim($countryInput))]);
        }

        // Compte créé avec une autre clé (test → live) : on repart de zéro.
        if (!empty($user->stripe_account_id)
            && ($this->stripe->accountState($user->stripe_account_id)['missing'] ?? false)) {
            Log::warning('[StripeConnectController] Compte Connect introuvable avec la clé actuelle — recréation', [
                'user_id' => $user->id,
                'old_account_id' => $user->stripe_account_id,
                'mode' => $this->stripe->mode(),
            ]);
            $this->forgetAccount($user);
        }

        if (empty($user->stripe_account_id)) {
            $validated = Validator::make($request->all(), [
                'country' => ['bail', 'required', 'string', 'regex:/^[A-Z]{2}$/'],
            ], [
                'country.required' => __('payments.stripe_connect.validation.country_required'),
                'country.regex' => __('payments.stripe_connect.validation.country_size'),
            ])->validate();

            $country = $validated['country'];
            $currency = $this->stripe->payoutCurrencyForCountry($country);
            if ($currency === null) {
                $message = __('payments.stripe_connect.error_country_unsupported');

                return response()->json([
                    'success' => false,
                    'message' => $message,
                    'errors' => ['country' => [$message]],
                ], 422);
            }

            $accountId = $this->stripe->createHostedAccount([
                'country' => $country,
                'currency' => $currency,
                'email' => $user->email ?: null,
                'display_name' => trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: null,
                'user_id' => $user->id,
            ]);

            $user->update([
                'stripe_account_id' => $accountId,
                'stripe_account_status' => null,
                'stripe_rejection_reason' => null,
                'stripe_submitted_at' => null,
                'stripe_verified_at' => null,
                'stripe_external_last4' => null,
                'stripe_bank_country' => $country,
                'stripe_account_holder_name' => null,
            ]);

            Log::info('[StripeConnectController] Compte Connect créé (inscription hébergée)', [
                'user_id' => $user->id,
                'account_id' => $accountId,
                'country' => $country,
                'mode' => $this->stripe->mode(),
            ]);
        } elseif ($user->stripe_account_status === 'approved'
            && ($this->stripe->accountState($user->stripe_account_id)['ready'] ?? false)) {
            // Compte validé et opérationnel : pas de nouvelle inscription.
            return response()->json([
                'success' => false,
                'message' => __('payments.stripe_connect.already_validated'),
            ], 422);
        } elseif ($user->stripe_account_status === 'rejected') {
            // Le vendeur corrige son dossier chez Stripe : il repassera en revue
            // dès que l'inscription sera de nouveau complète.
            $user->update([
                'stripe_account_status' => null,
                'stripe_rejection_reason' => null,
            ]);
        }

        return null;
    }

    /**
     * Ancien envoi du formulaire IBAN (versions de l'app antérieures à l'inscription
     * hébergée). La plateforme n'a plus le droit de collecter ces informations : on
     * indique au vendeur de mettre l'application à jour, sans rien envoyer à Stripe.
     * POST /v1/stripe/connect/submit
     */
    public function submit(Request $request)
    {
        return response()->json([
            'success' => false,
            'message' => __('payments.stripe_connect.hosted_onboarding_required'),
            'data' => ['onboarding_required' => true],
        ], 422);
    }

    /**
     * Désynchronise l'IBAN : le compte Stripe est fermé et le vendeur pourra en
     * ouvrir un nouveau (nouvelle inscription). Refusé tant qu'un virement est en
     * cours ou que des fonds restent sur le compte Stripe du vendeur.
     * DELETE /v1/stripe/connect
     */
    public function unlink(Request $request)
    {
        $user = $request->user();

        if (empty($user->stripe_account_id)
            && empty($user->stripe_account_status)
            && empty($user->stripe_external_last4)) {
            return response()->json([
                'success' => false,
                'message' => __('payments.stripe_connect.nothing_to_unlink'),
            ], 422);
        }

        $inProgress = \App\Models\PlatformWithdrawal::where('user_id', $user->id)
            ->where('provider', 'stripe')
            ->whereIn('status', ['pending', 'processing'])
            ->exists();
        if ($inProgress) {
            return response()->json([
                'success' => false,
                'message' => __('payments.stripe_connect.unlink_withdrawal_in_progress'),
            ], 422);
        }

        if (!empty($user->stripe_account_id) && $this->stripe->isConfigured()) {
            $state = $this->stripe->accountState($user->stripe_account_id);

            if ($state['exists'] ?? false) {
                if ($this->stripe->connectedHasFunds($user->stripe_account_id)) {
                    return response()->json([
                        'success' => false,
                        'message' => __('payments.stripe_connect.unlink_balance_remaining'),
                    ], 422);
                }

                try {
                    $this->stripe->closeAccount($user->stripe_account_id);
                } catch (\Throwable $e) {
                    if (!StripeService::isMissingAccountError($e)) {
                        Log::error('[StripeConnectController] Fermeture du compte Connect échouée', [
                            'user_id' => $user->id,
                            'account_id' => $user->stripe_account_id,
                            'exception' => $e::class,
                            'stripe_code' => $e instanceof \Stripe\Exception\ApiErrorException ? $e->getStripeCode() : null,
                            'stripe_message' => mb_substr($e->getMessage(), 0, 300),
                        ]);

                        return response()->json([
                            'success' => false,
                            'message' => __('payments.stripe_connect.unlink_failed'),
                        ], 503);
                    }
                }
            } elseif (!($state['missing'] ?? false)) {
                // Stripe injoignable : ne pas oublier un compte qu'on n'a pas pu fermer.
                return response()->json([
                    'success' => false,
                    'message' => __('payments.stripe_connect.error_connection'),
                ], 503);
            }
        }

        $this->forgetAccount($user);

        Log::info('[StripeConnectController] IBAN désynchronisé par l\'utilisateur', ['user_id' => $user->id]);

        return response()->json([
            'success' => true,
            'message' => __('payments.stripe_connect.unlinked'),
            'data' => $this->formatStatus($user->fresh()),
        ]);
    }

    /**
     * Page affichée par Stripe à la fin de l'inscription (le vendeur la ferme et
     * revient dans l'application, qui relit le statut).
     * GET /stripe/connect/return
     */
    public function returnPage()
    {
        return $this->statusPage('success');
    }

    /**
     * Lien d'inscription expiré ou déjà utilisé : Stripe renvoie ici, on génère un
     * nouveau lien pour le même vendeur (URL signée, valable 24 h).
     * GET /stripe/connect/refresh/{user}
     */
    public function refreshPage(Request $request, int $user)
    {
        $vendor = User::find($user);

        if ($vendor && !empty($vendor->stripe_account_id) && $this->stripe->isConfigured()) {
            try {
                $link = $this->stripe->createOnboardingLink(
                    $vendor->stripe_account_id,
                    $this->refreshUrl($vendor),
                    route('stripe.connect.return'),
                );

                return redirect()->away($link['url']);
            } catch (\Throwable $e) {
                Log::warning('[StripeConnectController] Nouveau lien d\'inscription impossible', [
                    'user_id' => $vendor->id,
                    'exception' => $e::class,
                    'stripe_code' => $e instanceof \Stripe\Exception\ApiErrorException ? $e->getStripeCode() : null,
                ]);
            }
        }

        return $this->statusPage('expired');
    }

    private function refreshUrl(User $user): string
    {
        return URL::temporarySignedRoute('stripe.connect.refresh', now()->addDay(), ['user' => $user->id]);
    }

    /** Page de statut aux couleurs d'ASSO (« success » ou « expired »). */
    private function statusPage(string $variant)
    {
        // Page ouverte dans le navigateur du téléphone : on suit sa langue.
        app()->setLocale(request()->getPreferredLanguage(['fr', 'en']) ?? 'fr');

        $key = $variant === 'success' ? 'return_page' : 'refresh_page';
        $t = fn (string $k) => __("payments.stripe_connect.{$key}.{$k}");

        return response()->view('stripe-connect.status-page', [
            'variant' => $variant,
            'title' => $t('title'),
            'body' => $t('body'),
            'steps' => $variant === 'success' ? [$t('step1'), $t('step2')] : [],
            'button' => $t('button'),
            'hint' => __('payments.stripe_connect.page_close_hint'),
            'secure' => __('payments.stripe_connect.page_secure'),
        ]);
    }

    /** Oublie le compte Stripe du vendeur (fermé, ou inexistant pour la clé actuelle). */
    private function forgetAccount(User $user): void
    {
        $user->update([
            'stripe_account_id' => null,
            'stripe_account_status' => null,
            'stripe_rejection_reason' => null,
            'stripe_submitted_at' => null,
            'stripe_verified_at' => null,
            'stripe_external_last4' => null,
            'stripe_bank_country' => null,
            'stripe_account_holder_name' => null,
        ]);
    }

    /**
     * Réponse d'erreur Stripe : détail technique dans le log, message clair au
     * vendeur. Une erreur de configuration de la plateforme n'est jamais présentée
     * comme une erreur de sa part.
     */
    private function stripeFailure(\Throwable $e, User $user, string $context)
    {
        $platformError = StripeService::isPlatformConfigError($e);

        Log::log($platformError ? 'critical' : 'error', "[StripeConnectController] {$context}", [
            'user_id' => $user->id,
            'mode' => $this->stripe->mode(),
            'exception' => $e::class,
            'stripe_code' => $e instanceof \Stripe\Exception\ApiErrorException ? $e->getStripeCode() : null,
            'stripe_param' => $e instanceof \Stripe\Exception\InvalidRequestException ? $e->getStripeParam() : null,
            // Aucune donnée personnelle n'est envoyée par la plateforme : le texte
            // Stripe peut être conservé pour le diagnostic.
            'stripe_message' => mb_substr($e->getMessage(), 0, 500),
        ]);

        if ($platformError
            || $e instanceof \Stripe\Exception\AuthenticationException
            || $e instanceof \Stripe\Exception\PermissionException
            || $e instanceof \RuntimeException) {
            return response()->json([
                'success' => false,
                'message' => __('payments.stripe_connect.error_unavailable'),
            ], 503);
        }

        if ($e instanceof \Stripe\Exception\ApiConnectionException
            || $e instanceof \Stripe\Exception\RateLimitException) {
            return response()->json([
                'success' => false,
                'message' => __('payments.stripe_connect.error_connection'),
            ], 503);
        }

        if ($e instanceof \Stripe\Exception\InvalidRequestException) {
            $raw = strtolower($e->getMessage());
            if (str_contains($raw, 'country')
                || str_contains($raw, 'not currently supported')
                || str_contains($raw, 'country_spec')) {
                $message = __('payments.stripe_connect.error_country_unsupported');

                return response()->json([
                    'success' => false,
                    'message' => $message,
                    'errors' => ['country' => [$message]],
                ], 422);
            }
        }

        return response()->json([
            'success' => false,
            'message' => __('payments.stripe_connect.error_onboarding_failed'),
        ], $e instanceof \Stripe\Exception\ApiErrorException ? 502 : 500);
    }

    /** Libellé de l'état de vérification Stripe, affichable tel quel par le mobile. */
    private function verificationLabel(array $state): string
    {
        if (!($state['exists'] ?? false)) {
            return __('payments.stripe_connect.status_unavailable');
        }
        if ($state['ready']) {
            return __('payments.stripe_connect.verified');
        }
        if (!($state['details_submitted'] ?? false)) {
            return __('payments.stripe_connect.onboarding_incomplete');
        }

        return match ($state['transfers']) {
            'pending' => __('payments.stripe_connect.pending'),
            'inactive', 'restricted' => empty($state['requirements_due'])
                ? __('payments.stripe_connect.not_activated')
                : __('payments.stripe_connect.requirements_due'),
            default => empty($state['requirements_due'])
                ? __('payments.stripe_connect.pending')
                : __('payments.stripe_connect.requirements_due'),
        };
    }

    /** Représentation publique du statut (jamais l'IBAN complet). */
    private function formatStatus($user): array
    {
        return [
            'status' => $user->stripe_account_status, // null | pending | approved | rejected
            'rejection_reason' => $user->stripe_rejection_reason,
            'submitted_at' => $user->stripe_submitted_at,
            'verified_at' => $user->stripe_verified_at,
            'iban_last4' => $user->stripe_external_last4,
            'bank_country' => $user->stripe_bank_country,
            'account_holder_name' => $user->stripe_account_holder_name,
            'has_account' => !empty($user->stripe_account_id),
            'onboarding' => 'stripe_hosted',
        ];
    }
}
