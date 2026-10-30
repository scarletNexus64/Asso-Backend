<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Stripe\StripeClient;

/**
 * Stripe Connect (comptes vendeurs, inscription hébergée par Stripe) + paiements carte.
 *
 * Wrapper mince du SDK Stripe : TOUT appel `\Stripe\*` reste encapsulé ici, afin
 * que les contrôleurs restent testables en mockant ce service (les tests n'ont
 * alors pas besoin de clés Stripe réelles).
 *
 * Config lue depuis `service_configurations` (service 'stripe'), avec fallback env :
 *   { publishable_key, secret_key, webhook_secret, mode }
 * Le mode (test|live) est déduit du préfixe de la clé secrète (sk_live_ / sk_test_).
 */
class StripeService
{
    private const VENDOR_PAYOUT_CURRENCIES = ['eur', 'gbp', 'usd'];

    private ?string $secretKey;
    private ?string $publishableKey;
    private ?string $webhookSecret;
    private ?string $webhookSecretConnect;
    /** @var string[] */
    private array $webhookSecretsExtra;
    private string $mode;
    private ?StripeClient $client = null;

    public function __construct()
    {
        $config = \App\Models\ServiceConfiguration::getConfig('stripe') ?? [];

        $this->secretKey = $config['secret_key'] ?? env('STRIPE_SECRET');
        $this->publishableKey = $config['publishable_key'] ?? env('STRIPE_KEY');
        $this->webhookSecret = $config['webhook_secret'] ?? env('STRIPE_WEBHOOK_SECRET');
        // Les événements Connect (payout.*, account.updated, émis sur les comptes
        // vendeurs) proviennent d'un endpoint Stripe DISTINCT, donc d'un secret de
        // signature distinct : les deux sont acceptés à la vérification.
        $this->webhookSecretConnect = $config['webhook_secret_connect'] ?? env('STRIPE_WEBHOOK_SECRET_CONNECT');
        // Endpoints supplémentaires vers la même URL (Stripe ne permet pas de relire
        // un secret : on garde ceux dont on ignore l'endpoint d'origine).
        $this->webhookSecretsExtra = array_filter((array) ($config['webhook_secrets_extra'] ?? []), 'is_string');
        // Le préfixe de la clé secrète fait foi : un champ `mode` resté sur « test »
        // à côté de clés live faisait passer la prod pour un environnement de test.
        $this->mode = self::keyMode($this->secretKey) ?? $config['mode'] ?? 'test';

        Log::debug('[StripeService] Initialized', [
            'mode' => $this->mode,
            'secret_key' => $this->secretKey ? 'SET' : 'NULL',
            'publishable_key' => $this->publishableKey ? 'SET' : 'NULL',
        ]);
    }

    /** Whether credentials are configured. */
    public function isConfigured(): bool
    {
        return !empty($this->secretKey);
    }

    public function mode(): string
    {
        return $this->mode;
    }

    public function publishableKey(): ?string
    {
        return $this->publishableKey;
    }

    /** Mode d'une clé Stripe d'après son préfixe (pk_live_, sk_test_, rk_live_…), null si inconnu. */
    public static function keyMode(?string $key): ?string
    {
        return match (true) {
            (bool) preg_match('/^(pk|sk|rk)_live_/', (string) $key) => 'live',
            (bool) preg_match('/^(pk|sk|rk)_test_/', (string) $key) => 'test',
            default => null,
        };
    }

    /**
     * Incohérence entre clé publique et clé secrète, ou null si la paire est utilisable.
     *
     * Le PaymentIntent est créé avec la clé secrète et confirmé par l'app avec la
     * clé publique : si elles ne sont pas du même mode (pk_test + sk_live…), ou si
     * la clé publique manque, la Payment Sheet ne s'ouvre jamais côté mobile.
     */
    public static function keyPairProblem(?string $publishableKey, ?string $secretKey): ?string
    {
        if (empty($publishableKey)) {
            return 'Clé publique Stripe (pk_…) manquante.';
        }
        $public = self::keyMode($publishableKey);
        $secret = self::keyMode($secretKey);
        if ($public !== null && $secret !== null && $public !== $secret) {
            return "Clés Stripe incohérentes : clé publique en mode {$public}, clé secrète en mode {$secret}.";
        }
        return null;
    }

    /** Incohérence de la paire de clés configurée, ou null. */
    public function keysProblem(): ?string
    {
        return self::keyPairProblem($this->publishableKey, $this->secretKey);
    }

    /** Alias historique de keysProblem() (utilisé par stripe:doctor). */
    public function keysMismatch(): ?string
    {
        return $this->keysProblem();
    }

    /**
     * L'erreur Stripe indique-t-elle un compte Connect inexistant pour la clé
     * actuelle ? Cas typique : compte créé en mode test, puis passage en live.
     */
    public static function isMissingAccountError(\Throwable $e): bool
    {
        $message = strtolower($e->getMessage());

        return ($e instanceof \Stripe\Exception\ApiErrorException && $e->getStripeCode() === 'account_invalid')
            || str_contains($message, 'does not have access to account')
            || str_contains($message, 'no such account');
    }

    /**
     * L'erreur Stripe vient-elle de la configuration de la PLATEFORME (et non des
     * données saisies par le vendeur) ?
     */
    public static function isPlatformConfigError(\Throwable $e): bool
    {
        // Codes propres à la configuration Connect de la plateforme : le vendeur n'y
        // peut rien. Sans ce test, « account_creation_requirement_collection_
        // unacknowledged » était présenté au vendeur comme « informations invalides ».
        $code = $e instanceof \Stripe\Exception\ApiErrorException ? (string) $e->getStripeCode() : '';
        if (in_array($code, [
            'account_creation_requirement_collection_unacknowledged',
            'accounts_v2_access_blocked',
            'account_dashboard_not_supported',
            'configs_must_match_to_use_account_links',
        ], true) || str_contains($code, 'unacknowledged')) {
            return true;
        }

        $message = strtolower($e->getMessage());

        return str_contains($message, 'platform profile')
            || str_contains($message, 'test bank account')
            || str_contains($message, 'livemode')
            || str_contains($message, 'signed up for connect')
            || str_contains($message, 'accounts v1')
            || str_contains($message, 'stripe-hosted onboarding')
            || str_contains($message, 'requirement collection')
            || str_contains($message, 'acknowledge');
    }

    public function webhookSecret(): ?string
    {
        return $this->webhookSecret;
    }

    public function webhookSecretConnect(): ?string
    {
        return $this->webhookSecretConnect;
    }

    /** Secrets de signature acceptés (compte plateforme + Connect + extras), sans doublon. */
    public function webhookSecrets(): array
    {
        return array_values(array_unique(array_filter([
            $this->webhookSecret,
            $this->webhookSecretConnect,
            ...$this->webhookSecretsExtra,
        ])));
    }

    /** Lazily build the Stripe SDK client. */
    private function client(): StripeClient
    {
        if (!$this->isConfigured()) {
            throw new \RuntimeException('Clés API Stripe manquantes.');
        }
        return $this->client ??= new StripeClient($this->secretKey);
    }

    /**
     * Exécute un appel du SDK Stripe en neutralisant les « stripe-notice »
     * (avertissements E_USER_WARNING émis par le SDK, ex. la recommandation
     * « Accounts v2 » renvoyée dans un en-tête de réponse v1). Sans cela,
     * Laravel convertit cet avertissement en ErrorException qui court-circuite
     * et MASQUE la véritable ApiErrorException (ex. IBAN de test invalide),
     * affichant un message trompeur à l'utilisateur.
     */
    private function withoutStripeNotices(callable $fn)
    {
        set_error_handler(static fn (): bool => true, \E_USER_WARNING);
        try {
            return $fn();
        } finally {
            restore_error_handler();
        }
    }

    /**
     * Test the API credentials (used by the admin config panel).
     * Retrieves the platform account — echoes back the account id / country.
     */
    public function testConnection(): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'message' => 'Clés API Stripe manquantes.'];
        }
        if ($problem = $this->keysProblem()) {
            return ['success' => false, 'message' => $problem];
        }
        try {
            $account = $this->client()->accounts->retrieve();
            return [
                'success' => true,
                'message' => "Connexion Stripe réussie (mode {$this->mode}, compte {$account->id}).",
                'data' => ['account_id' => $account->id, 'country' => $account->country ?? null],
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Erreur Stripe: ' . $e->getMessage()];
        }
    }

    /**
     * Crée le compte Connect d'un vendeur dont les informations (identité, IBAN)
     * seront collectées par Stripe sur sa page d'inscription hébergée.
     *
     * Pourquoi l'inscription hébergée : en France, la réglementation interdit à la
     * plateforme de collecter elle-même ces informations (« you can only create
     * connected accounts using Stripe-hosted onboarding »). Avec Accounts v2, c'est
     * le couple `dashboard` + `losses_collector` qui décide qui les collecte : seul
     * `dashboard = none` + `losses_collector = application` laisse la collecte à la
     * plateforme. Avec `dashboard = express`, Stripe la prend en charge, tandis que
     * la plateforme garde frais et pertes (même modèle de fonds qu'avant).
     *
     * @param array $data country(2), currency, email?, display_name?, user_id?
     * @return string Identifiant du compte (acct_…)
     */
    public function createHostedAccount(array $data): string
    {
        $country = strtolower((string) $data['country']);
        $currency = strtolower((string) ($data['currency'] ?? ''));
        if ($currency === '') {
            throw new \DomainException('A supported payout currency is unavailable for this country.');
        }
        $businessProfile = $this->businessProfileParams();

        $account = $this->withoutStripeNotices(fn () => $this->client()->v2->core->accounts->create(array_filter([
            'contact_email' => $data['email'] ?? null,
            'display_name' => $data['display_name'] ?? null,
            // Le pays est figé à la création ; le reste de l'identité (particulier ou
            // entreprise, pièces, IBAN) est demandé par Stripe pendant l'inscription.
            'identity' => ['country' => $country],
            'configuration' => [
                'recipient' => [
                    'capabilities' => [
                        'stripe_balance' => [
                            'stripe_transfers' => ['requested' => true],
                        ],
                    ],
                ],
            ],
            'defaults' => [
                'currency' => $currency,
                'profile' => array_filter([
                    'business_url' => $businessProfile['url'] ?? null,
                    'product_description' => $businessProfile['product_description'] ?? null,
                ]),
                'responsibilities' => [
                    'fees_collector' => 'application',
                    'losses_collector' => 'application',
                ],
            ],
            'dashboard' => 'express',
            'metadata' => array_filter([
                'asso_user_id' => (string) ($data['user_id'] ?? ''),
                'asso_connect_api_version' => 'v2',
                'asso_onboarding' => 'hosted',
            ]),
            'include' => ['configuration.recipient', 'identity', 'defaults'],
        ], static fn ($value) => $value !== null)));

        $this->ensureManualPayouts($account->id);

        return $account->id;
    }

    /**
     * Force le versement **manuel** du compte connecté : c'est TOUJOURS nous qui
     * créons le Payout, donc nous connaissons son id et le webhook `payout.paid`
     * peut le rapprocher du retrait. En automatique, Stripe créerait des payouts
     * inconnus de notre base et le retrait resterait `processing`.
     *
     * Best-effort : rejoué à chaque synchronisation tant qu'il n'a pas abouti.
     */
    public function ensureManualPayouts(string $accountId): bool
    {
        try {
            $this->withoutStripeNotices(fn () => $this->client()->accounts->update($accountId, [
                'settings' => ['payouts' => ['schedule' => ['interval' => 'manual']]],
            ]));

            return true;
        } catch (\Throwable $e) {
            Log::warning('[StripeService] Versement manuel non appliqué au compte connecté', [
                'account_id' => $accountId,
                'exception' => $e::class,
                'stripe_code' => $e instanceof \Stripe\Exception\ApiErrorException ? $e->getStripeCode() : null,
            ]);

            return false;
        }
    }

    /**
     * Lien à usage unique vers la page d'inscription Stripe du vendeur (quelques
     * minutes de validité). Toutes les informations exigibles sont demandées d'un
     * coup (`eventually_due`) pour éviter de le renvoyer plus tard sur la page.
     *
     * @return array{url:string, expires_at:?string}
     */
    public function createOnboardingLink(string $accountId, string $refreshUrl, string $returnUrl): array
    {
        $link = $this->withoutStripeNotices(fn () => $this->client()->v2->core->accountLinks->create([
            'account' => $accountId,
            'use_case' => [
                'type' => 'account_onboarding',
                'account_onboarding' => [
                    'configurations' => ['recipient'],
                    'collection_options' => ['fields' => 'eventually_due'],
                    'refresh_url' => $refreshUrl,
                    'return_url' => $returnUrl,
                ],
            ],
        ]));

        return [
            'url' => (string) $link->url,
            'expires_at' => isset($link->expires_at) ? (string) $link->expires_at : null,
        ];
    }

    /**
     * Session du composant d'inscription intégré (SDK Stripe Connect iOS/Android) :
     * le vendeur saisit identité et IBAN dans l'application, sous l'apparence
     * d'ASSO. Stripe reste responsable de la collecte (obligation réglementaire),
     * d'où l'authentification Stripe parfois demandée et non désactivable.
     *
     * @return array{client_secret:string, expires_at:?int}
     */
    public function createOnboardingSession(string $accountId): array
    {
        $session = $this->withoutStripeNotices(fn () => $this->client()->accountSessions->create([
            'account' => $accountId,
            'components' => [
                'account_onboarding' => [
                    'enabled' => true,
                    'features' => ['external_account_collection' => true],
                ],
            ],
        ]));

        return [
            'client_secret' => (string) $session->client_secret,
            'expires_at' => isset($session->expires_at) ? (int) $session->expires_at : null,
        ];
    }

    /**
     * IBAN enregistré par le vendeur sur la page Stripe (jamais le numéro complet).
     *
     * @return array{last4:?string, country:?string, holder:?string}|null null si aucun.
     */
    public function bankAccountSummary(string $accountId): ?array
    {
        try {
            $accounts = $this->withoutStripeNotices(fn () => $this->client()->accounts->allExternalAccounts(
                $accountId,
                ['object' => 'bank_account', 'limit' => 10],
            ));
        } catch (\Throwable $e) {
            Log::warning('[StripeService] Lecture de l\'IBAN du compte connecté échouée', [
                'account_id' => $accountId,
                'exception' => $e::class,
            ]);

            return null;
        }

        $banks = $accounts->data ?? [];
        if (empty($banks)) {
            return null;
        }

        $bank = $banks[0];
        foreach ($banks as $candidate) {
            if (!empty($candidate->default_for_currency)) {
                $bank = $candidate;
                break;
            }
        }

        return [
            'last4' => $bank->last4 ?? null,
            'country' => isset($bank->country) ? strtoupper((string) $bank->country) : null,
            'holder' => $bank->account_holder_name ?? null,
        ];
    }

    /** Ferme un compte connecté (désynchronisation de l'IBAN par le vendeur). */
    public function closeAccount(string $accountId): void
    {
        $this->withoutStripeNotices(fn () => $this->client()->v2->core->accounts->close($accountId));
    }

    /**
     * Resolve a Stripe-supported bank currency that this application can pay out.
     *
     * Country Specs are Stripe's source of truth for bank-account currencies. The
     * intersection with currencies accepted by payoutToVendor prevents creating an
     * IBAN that the current withdrawal pipeline cannot pay.
     */
    public function payoutCurrencyForCountry(string $country): ?string
    {
        $country = strtoupper($country);
        $spec = $this->withoutStripeNotices(
            fn () => $this->client()->countrySpecs->retrieve($country)
        );
        $supportedCurrencies = $spec->supported_bank_account_currencies ?? [];
        $supportedCurrencies = $supportedCurrencies instanceof \Stripe\StripeObject
            ? $supportedCurrencies->toArray()
            : (array) $supportedCurrencies;
        $eligibleCurrencies = [];
        foreach ($supportedCurrencies as $currency => $countries) {
            $currency = strtolower((string) $currency);
            if (in_array($currency, self::VENDOR_PAYOUT_CURRENCIES, true)
                && in_array($country, array_map('strtoupper', (array) $countries), true)) {
                $eligibleCurrencies[] = $currency;
            }
        }

        if ($eligibleCurrencies === []) {
            return null;
        }

        $defaultCurrency = strtolower((string) ($spec->default_currency ?? ''));

        return in_array($defaultCurrency, $eligibleCurrencies, true)
            ? $defaultCurrency
            : $eligibleCurrencies[0];
    }

    /**
     * État RÉEL du compte Connect côté Stripe (source d'autorité pour savoir si un
     * virement est possible), utilisé par la validation admin et le retrait.
     *
     * @return array {
     *   exists: bool, transfers: string (active|pending|inactive|unknown),
     *   payouts_enabled: bool, charges_enabled: bool,
     *   requirements_due: string[], disabled_reason: ?string, ready: bool, error: ?string
     * }
     */
    public function accountState(string $accountId): array
    {
        $unknown = [
            'exists' => false,
            'transfers' => 'unknown',
            'payouts_enabled' => false,
            'charges_enabled' => false,
            'requirements_due' => [],
            'disabled_reason' => null,
            'details_submitted' => false,
            'ready' => false,
            'error' => null,
            // true = le compte n'existe pas pour la clé actuelle (ex. créé en test).
            'missing' => false,
        ];

        try {
            $account = $this->retrieveAccount($accountId);
        } catch (\Throwable $e) {
            Log::warning('[StripeService] accountState échoué', [
                'account_id' => $accountId,
                'error' => $e->getMessage(),
            ]);
            return array_merge($unknown, [
                'error' => $e->getMessage(),
                'missing' => self::isMissingAccountError($e),
            ]);
        }

        $isV2 = ($account->metadata->asso_connect_api_version ?? null) === 'v2';
        if ($isV2) {
            try {
                $v2Account = $this->withoutStripeNotices(
                    fn () => $this->client()->v2->core->accounts->retrieve($accountId, [
                        'include' => ['configuration.recipient', 'identity', 'defaults'],
                    ])
                );
            } catch (\Throwable $e) {
                Log::warning('[StripeService] Lecture Accounts v2 échouée', [
                    'account_id' => $accountId,
                    'exception' => $e::class,
                    'stripe_code' => $e instanceof \Stripe\Exception\ApiErrorException
                        ? $e->getStripeCode()
                        : null,
                ]);

                return array_merge($unknown, [
                    'exists' => true,
                    'error' => 'Connected account status is temporarily unavailable.',
                ]);
            }
            $recipient = $v2Account->configuration->recipient ?? null;
            $capabilities = $recipient->capabilities->stripe_balance ?? null;
            $transfers = (string) ($capabilities->stripe_transfers->status ?? 'unknown');
            $payoutsEnabled = (string) ($capabilities->payouts->status ?? 'unknown') === 'active';
        } else {
            $transfers = (string) ($account->capabilities->transfers ?? 'unknown');
            $payoutsEnabled = (bool) ($account->payouts_enabled ?? false);
        }

        // The interoperable v1 account exposes currently_due/past_due for both
        // account generations. V2 requirements use entries, not these arrays.
        $due = array_values(array_unique(array_merge(
            (array) ($account->requirements->currently_due ?? []),
            (array) ($account->requirements->past_due ?? []),
        )));
        $disabledReason = $account->requirements->disabled_reason ?? null;

        return [
            'exists' => true,
            'transfers' => $transfers,
            'payouts_enabled' => $payoutsEnabled,
            'charges_enabled' => (bool) ($account->charges_enabled ?? false),
            'requirements_due' => $due,
            'disabled_reason' => $disabledReason,
            // Le vendeur a terminé la page d'inscription Stripe.
            'details_submitted' => (bool) ($account->details_submitted ?? false),
            'payout_interval' => $account->settings->payouts->schedule->interval ?? null,
            // `ready` = Stripe accepterait un Transfer + un Payout vers l'IBAN.
            'ready' => $transfers === 'active' && $payoutsEnabled,
            'error' => null,
            'missing' => false,
        ];
    }

    /**
     * Solde DISPONIBLE de la plateforme dans une devise donnée (unité principale).
     *
     * Le solde Stripe est par devise : un compte plateforme canadien qui encaisse en
     * EUR voit ses fonds convertis en CAD, et un Transfer en EUR échoue alors même
     * que le solde global est positif. On vérifie donc la devise AVANT de débiter le
     * wallet du vendeur.
     */
    public function platformAvailableBalance(string $currency): float
    {
        $currency = strtolower($currency);

        try {
            $balance = $this->withoutStripeNotices(fn () => $this->client()->balance->retrieve());
        } catch (\Throwable $e) {
            Log::warning('[StripeService] Lecture du solde plateforme échouée', [
                'error' => $e->getMessage(),
            ]);
            return 0.0;
        }

        foreach (($balance->available ?? []) as $entry) {
            if (strtolower($entry->currency ?? '') === $currency) {
                return ((int) ($entry->amount ?? 0)) / 100;
            }
        }

        return 0.0;
    }

    /**
     * La plateforme a-t-elle de quoi payer, toutes devises confondues ? (cache 5 min)
     *
     * Sert à GRISER le rail « virement bancaire » côté application. La devise n'est
     * plus un critère : un solde en CAD finance un virement en EUR, la conversion
     * étant faite au transfert. Seule l'absence totale de fonds bloque.
     */
    public function platformSupportsCurrency(string $currency): bool
    {
        return \Illuminate\Support\Facades\Cache::remember(
            'stripe:platform_capacity_' . strtoupper($currency),
            300,
            fn () => $this->platformPayoutCapacity($currency) > 0,
        );
    }

    /**
     * Crédite le solde de la plateforme en MODE TEST, via la carte de test dont les
     * fonds sont immédiatement disponibles (4000 0000 0000 0077).
     *
     * Une carte de test ordinaire (4242…) crédite le solde « pending » : les payouts
     * resteraient impossibles. Réservé aux clés de test — la garde est dans la
     * commande appelante.
     *
     * @return array{success:bool, charge_id?:string, message?:string}
     */
    public function fundTestBalance(float $amount, string $currency): array
    {
        try {
            $charge = $this->withoutStripeNotices(fn () => $this->client()->charges->create([
                'amount' => (int) round($amount * 100),
                'currency' => strtolower($currency),
                'source' => 'tok_bypassPending', // carte 4000 0000 0000 0077
                'description' => 'ASSO — alimentation du solde de test',
            ]));

            return ['success' => true, 'charge_id' => $charge->id];
        } catch (\Throwable $e) {
            Log::error('[StripeService] Alimentation du solde de test échouée', [
                'error' => $e->getMessage(),
            ]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /** Devises dans lesquelles la plateforme détient un solde (pour les diagnostics). */
    public function platformBalanceCurrencies(): array
    {
        try {
            $balance = $this->withoutStripeNotices(fn () => $this->client()->balance->retrieve());
        } catch (\Throwable $e) {
            return [];
        }

        $out = [];
        foreach (($balance->available ?? []) as $entry) {
            $out[strtoupper($entry->currency ?? '')] = ((int) ($entry->amount ?? 0)) / 100;
        }

        return $out;
    }

    public function retrieveAccount(string $accountId): \Stripe\Account
    {
        return $this->withoutStripeNotices(fn () => $this->client()->accounts->retrieve($accountId));
    }

    /** Objet payout complet (réconciliation) ; null si introuvable. */
    public function retrievePayout(string $accountId, string $payoutId): ?\Stripe\Payout
    {
        try {
            return $this->withoutStripeNotices(fn () => $this->client()->payouts->retrieve(
                $payoutId,
                [],
                ['stripe_account' => $accountId]
            ));
        } catch (\Throwable $e) {
            Log::warning('[StripeService] Payout introuvable', [
                'account_id' => $accountId,
                'payout_id' => $payoutId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Statut d'un payout sur le compte connecté (réconciliation du virement IBAN).
     *
     * @return array { success: bool, status?: string, failure_code?: string, data?: array, message?: string }
     *   status Stripe : paid | pending | in_transit | canceled | failed
     *   failure_code (si failed) : no_account | account_closed | insufficient_funds |
     *                              debit_not_authorized | invalid_currency | could_not_process | ...
     */
    public function getPayoutStatus(string $accountId, string $payoutId): array
    {
        try {
            $payout = $this->withoutStripeNotices(fn () => $this->client()->payouts->retrieve(
                $payoutId,
                [],
                ['stripe_account' => $accountId]
            ));

            return [
                'success' => true,
                'status' => $payout->status ?? null,
                'failure_code' => $payout->failure_code ?? null,
                'data' => $payout->toArray(),
            ];
        } catch (\Throwable $e) {
            Log::warning('[StripeService] getPayoutStatus échoué', [
                'account_id' => $accountId,
                'payout_id' => $payoutId,
                'error' => $e->getMessage(),
            ]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Contre-passe (annule) un Transfer plateforme → compte Connect.
     *
     * Utilisé quand le Payout vers l'IBAN échoue : le Transfer (autoritatif) avait
     * déjà déplacé les fonds vers le solde Connect du vendeur ; sans reversal, recréditer
     * le wallet créditerait le vendeur deux fois. Le reversal ramène les fonds côté
     * plateforme. Nécessite un solde Connect suffisant (les fonds d'un payout échoué y
     * sont retournés).
     *
     * @param int|null $amountMinor Montant à contre-passer (plus petite unité) ; null = total.
     * @return array { success: bool, reversal_id?: string, message?: string }
     */
    public function reverseTransfer(string $transferId, ?int $amountMinor = null): array
    {
        try {
            $params = [];
            if ($amountMinor !== null && $amountMinor > 0) {
                $params['amount'] = $amountMinor;
            }
            $reversal = $this->withoutStripeNotices(
                fn () => $this->client()->transfers->createReversal($transferId, $params)
            );
            return ['success' => true, 'reversal_id' => $reversal->id];
        } catch (\Throwable $e) {
            Log::error('[StripeService] Reversal du transfer échoué', [
                'transfer_id' => $transferId,
                'error' => $e->getMessage(),
            ]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Vérifie la signature d'un webhook Stripe et retourne l'événement typé.
     * Lève une exception si la signature est invalide ou le secret manquant.
     */
    public function constructWebhookEvent(string $payload, string $sigHeader): \Stripe\Event
    {
        $secrets = $this->webhookSecrets();

        if (empty($secrets)) {
            throw new \RuntimeException('Webhook secret Stripe non configuré.');
        }

        // Un événement n'est signé que par UN des endpoints : on essaie chaque secret
        // et on ne relaie l'échec que si aucun ne valide la signature.
        $last = null;
        foreach ($secrets as $secret) {
            try {
                return \Stripe\Webhook::constructEvent($payload, $sigHeader, $secret);
            } catch (\Throwable $e) {
                $last = $e;
            }
        }

        throw $last;
    }

    /**
     * Verse un montant au vendeur sur son IBAN, quelle que soit la devise détenue
     * par la plateforme.
     *
     * La plateforme encaisse en CAD/USD/… alors que l'IBAN du vendeur est en EUR :
     * plutôt que de refuser, on FINANCE le virement depuis la devise disponible et
     * on laisse Stripe convertir à l'arrivée sur le compte du vendeur.
     *
     *  1) On regarde ce que le compte du vendeur détient déjà dans la devise cible
     *     (reliquat d'une conversion précédente) et on ne transfère que le manque.
     *  2) **Transfer** plateforme → compte Connect : dans la devise cible si la
     *     plateforme la détient, sinon dans sa devise de règlement, montant converti
     *     au taux du moment plus une marge (`stripe_fx_buffer_percent`, 3 % par
     *     défaut) qui absorbe l'écart entre notre taux et celui de Stripe.
     *  3) **Payout** compte Connect → IBAN, du montant demandé — ou de ce qui est
     *     réellement disponible si le change a été moins favorable que prévu. Le
     *     montant effectivement versé est retourné à l'appelant.
     *
     * Si le payout est refusé, le transfer est contre-passé : aucun argent ne reste
     * bloqué sur le compte du vendeur.
     *
     * ⚠️ Devises de payout supportées : EUR/GBP/USD (2 décimales).
     *
     * @return array { id, transfer_id, payout_id, amount_minor, currency,
     *                 payout_amount, funding_currency, funding_amount, shortfall }
     */
    public function payoutToVendor(string $accountId, float $amount, string $currency): array
    {
        $currency = strtolower($currency);
        $minor = (int) round($amount * 100);

        if ($minor <= 0) {
            throw new \InvalidArgumentException(__('payments.invalid_payout_amount'));
        }

        $this->assertPayoutPossible($accountId, $amount, $currency);

        // 1) Ce que le compte du vendeur détient déjà dans la devise du virement.
        $connected = $this->connectedAvailableBalance($accountId, $currency);
        $missing = round($amount - $connected, 2);

        $transferId = null;
        $funding = null;

        // 2) Financement du manque, converti si nécessaire.
        if ($missing > 0) {
            $funding = $this->fundingFor($missing, $currency);

            try {
                $transfer = $this->withoutStripeNotices(fn () => $this->client()->transfers->create([
                    'amount' => (int) round($funding['amount'] * 100),
                    'currency' => $funding['currency'],
                    'destination' => $accountId,
                    'metadata' => [
                        'asso_kind' => 'vendor_withdrawal',
                        'target_currency' => strtoupper($currency),
                        'target_amount' => (string) $amount,
                    ],
                ]));
                $transferId = $transfer->id;
            } catch (\Throwable $e) {
                Log::error('[StripeService] Transfer plateforme → vendeur refusé', [
                    'account' => $accountId,
                    'funding' => $funding,
                    'error' => $e->getMessage(),
                ]);

                $raw = strtolower($e->getMessage());
                throw new \App\Exceptions\StripePayoutUnavailableException(
                    str_contains($raw, 'insufficient') || str_contains($raw, 'balance')
                        ? 'platform_funds'
                        : 'transfer_refused',
                    'Le transfert vers le compte du vendeur a été refusé : ' . $e->getMessage(),
                    ['account_id' => $accountId, 'funding' => $funding],
                );
            }

            // Relire le solde : Stripe a converti à SON taux.
            $connected = $this->connectedAvailableBalance($accountId, $currency);
        }

        // 3) Payout : le montant demandé, ou tout le disponible si le change a été
        // moins favorable que la marge prévue (mieux vaut verser un peu moins que
        // de tout annuler ; l'écart est tracé et le reliquat sert au prochain virement).
        $payoutAmount = min($amount, $connected);
        $payoutMinor = (int) floor(round($payoutAmount, 2) * 100);

        if ($payoutMinor <= 0) {
            if ($transferId) {
                $this->reverseTransfer($transferId);
            }
            throw new \App\Exceptions\StripePayoutUnavailableException(
                'payout_refused',
                "Le compte du vendeur n'a pas été crédité par la conversion.",
                ['account_id' => $accountId, 'funding' => $funding],
            );
        }

        try {
            $payout = $this->withoutStripeNotices(fn () => $this->client()->payouts->create([
                'amount' => $payoutMinor,
                'currency' => $currency,
                'metadata' => array_filter([
                    'asso_kind' => 'vendor_withdrawal',
                    'transfer_id' => $transferId,
                ]),
            ], ['stripe_account' => $accountId]));
        } catch (\Throwable $e) {
            // Sans payout, les fonds resteraient sur le compte Connect sans jamais
            // partir (versement manuel) : on les ramène côté plateforme.
            Log::error('[StripeService] Payout refusé — contre-passation du transfer', [
                'account' => $accountId,
                'transfer_id' => $transferId,
                'error' => $e->getMessage(),
            ]);

            if ($transferId) {
                $this->reverseTransfer($transferId);
            }

            throw new \App\Exceptions\StripePayoutUnavailableException(
                'payout_refused',
                "Le versement vers l'IBAN a été refusé par Stripe : " . $e->getMessage(),
                ['account_id' => $accountId, 'transfer_id' => $transferId],
            );
        }

        $paid = $payoutMinor / 100;

        return [
            'id' => $payout->id,
            'transfer_id' => $transferId,
            'payout_id' => $payout->id,
            'amount_minor' => $payoutMinor,
            'currency' => $currency,
            'payout_amount' => $paid,
            'funding_currency' => $funding['currency'] ?? $currency,
            'funding_amount' => $funding['amount'] ?? 0.0,
            // Écart éventuel entre le montant promis et le montant versé.
            'shortfall' => round($amount - $paid, 2),
        ];
    }

    /**
     * Détermine comment financer $missing dans la devise $currency.
     *
     * Devise cible si la plateforme la détient ; sinon sa devise de règlement, avec
     * le montant converti au taux du moment plus une marge de sécurité — Stripe
     * appliquera son propre taux à la conversion, généralement un peu moins bon.
     *
     * @return array{currency:string, amount:float, rate:float, buffer:float}
     * @throws \App\Exceptions\StripePayoutUnavailableException
     */
    private function fundingFor(float $missing, string $currency): array
    {
        $currency = strtolower($currency);
        $balances = $this->platformBalanceCurrencies();

        // 1) La plateforme détient déjà la devise du virement.
        if (($balances[strtoupper($currency)] ?? 0) >= $missing) {
            return ['currency' => $currency, 'amount' => $missing, 'rate' => 1.0, 'buffer' => 0.0];
        }

        // 2) Sinon : la devise de règlement (le plus gros solde disponible).
        arsort($balances);
        $bufferPercent = (float) \App\Models\Setting::get('stripe_fx_buffer_percent', 3);

        foreach ($balances as $code => $available) {
            $conversion = \App\Services\ExchangeRateService::convert($currency, strtolower($code), $missing);
            if (!($conversion['success'] ?? false)) {
                continue; // taux inconnu : on n'invente pas de conversion.
            }

            $needed = round(((float) $conversion['amount']) * (1 + $bufferPercent / 100), 2);

            if ($available + 0.0001 >= $needed) {
                return [
                    'currency' => strtolower($code),
                    'amount' => $needed,
                    'rate' => (float) $conversion['rate'],
                    'buffer' => $bufferPercent,
                ];
            }
        }

        throw new \App\Exceptions\StripePayoutUnavailableException(
            'platform_funds',
            sprintf('Aucun solde plateforme ne permet de financer %.2f %s.', $missing, strtoupper($currency)),
            ['needed' => $missing, 'currency' => strtoupper($currency), 'balances' => $balances],
        );
    }

    /** Solde disponible d'un compte connecté dans une devise (unité principale). */
    public function connectedAvailableBalance(string $accountId, string $currency): float
    {
        $currency = strtolower($currency);

        try {
            $balance = $this->withoutStripeNotices(
                fn () => $this->client()->balance->retrieve([], ['stripe_account' => $accountId])
            );
        } catch (\Throwable $e) {
            Log::warning('[StripeService] Solde du compte connecté illisible', [
                'account' => $accountId,
                'error' => $e->getMessage(),
            ]);
            return 0.0;
        }

        foreach (($balance->available ?? []) as $entry) {
            if (strtolower($entry->currency ?? '') === $currency) {
                return ((int) ($entry->amount ?? 0)) / 100;
            }
        }

        return 0.0;
    }

    /**
     * Le compte connecté détient-il encore des fonds (disponibles ou en attente,
     * toutes devises) ? Dans le doute (Stripe illisible), on répond oui : fermer
     * le compte bloquerait ces fonds.
     */
    public function connectedHasFunds(string $accountId): bool
    {
        try {
            $balance = $this->withoutStripeNotices(
                fn () => $this->client()->balance->retrieve([], ['stripe_account' => $accountId])
            );
        } catch (\Throwable $e) {
            if (self::isMissingAccountError($e)) {
                return false;
            }
            Log::warning('[StripeService] Solde du compte connecté illisible', [
                'account' => $accountId,
                'exception' => $e::class,
            ]);

            return true;
        }

        foreach (['available', 'pending'] as $bucket) {
            foreach (($balance->{$bucket} ?? []) as $entry) {
                if ((int) ($entry->amount ?? 0) > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Capacité de virement de la plateforme, exprimée dans la devise demandée :
     * somme de TOUS ses soldes convertis. Une devise sans taux connu est ignorée.
     */
    public function platformPayoutCapacity(string $currency): float
    {
        $currency = strtolower($currency);
        $total = 0.0;

        foreach ($this->platformBalanceCurrencies() as $code => $available) {
            if ($available <= 0) {
                continue;
            }

            if (strtolower($code) === $currency) {
                $total += $available;
                continue;
            }

            $conversion = \App\Services\ExchangeRateService::convert(strtolower($code), $currency, $available);
            if ($conversion['success'] ?? false) {
                $total += (float) $conversion['amount'];
            }
        }

        return round($total, 2);
    }

    /**
     * Vérifie que le virement est possible AVANT de toucher à l'argent : compte
     * vendeur activé par Stripe, et plateforme capable de financer le montant —
     * toutes devises confondues, la conversion étant assurée au moment du transfert.
     *
     * @throws \App\Exceptions\StripePayoutUnavailableException
     */
    public function assertPayoutPossible(string $accountId, float $amount, string $currency): void
    {
        $currency = strtolower($currency);

        // a) Le compte vendeur doit être réellement activé côté Stripe.
        $state = $this->accountState($accountId);
        if (!$state['ready']) {
            throw new \App\Exceptions\StripePayoutUnavailableException(
                'account_not_ready',
                "Le compte de virement du vendeur n'est pas encore activé par Stripe.",
                [
                    'account_id' => $accountId,
                    'transfers' => $state['transfers'],
                    'requirements_due' => $state['requirements_due'],
                    'disabled_reason' => $state['disabled_reason'],
                ],
            );
        }

        // b) La plateforme doit pouvoir financer le montant, quelle que soit la
        // devise de ses fonds (le compte du vendeur peut déjà en détenir une partie).
        $capacity = $this->platformPayoutCapacity($currency)
            + $this->connectedAvailableBalance($accountId, $currency);

        if ($capacity + 0.0001 < $amount) {
            throw new \App\Exceptions\StripePayoutUnavailableException(
                'platform_funds',
                sprintf(
                    'Fonds plateforme insuffisants : %.2f %s disponibles (toutes devises), %.2f requis.',
                    $capacity,
                    strtoupper($currency),
                    $amount,
                ),
                ['currency' => strtoupper($currency), 'capacity' => $capacity, 'balances' => $this->platformBalanceCurrencies()],
            );
        }
    }

    /**
     * Crée un PaymentIntent pour ENCAISSER un paiement carte (réservation / achat).
     *
     * Montant exprimé dans l'unité principale de la devise (ex. dollars) et converti
     * ici en plus petite unité : centimes pour USD/EUR/GBP, unité entière pour les
     * devises sans décimales de Stripe (XAF, XOF, JPY…).
     * Moyen de paiement explicite `card` : la Payment Sheet mobile ne propose que la
     * carte (Apple Pay / Google Pay compris), et le paiement ne dépend pas des moyens
     * de paiement activés dans le tableau de bord Stripe (sinon : « No valid payment
     * method types for this Payment Intent »). La carte est confirmée côté client avec
     * le `client_secret` ; la confirmation serveur passe par retrievePaymentIntent()
     * (polling) et/ou le webhook payment_intent.succeeded.
     *
     * @return array { id, client_secret, amount_minor, currency, publishable_key }
     */
    public function createPaymentIntent(float $amount, string $currency, array $metadata = []): array
    {
        $currency = strtolower($currency);
        $minor = self::toMinorUnits($amount, $currency);

        if ($minor <= 0) {
            throw new \InvalidArgumentException(__('payments.invalid_amount'));
        }

        // Inutile de créer une intention que l'app ne pourra pas confirmer.
        if ($problem = $this->keysProblem()) {
            Log::error('[StripeService] Paiement carte impossible : ' . $problem, ['mode' => $this->mode]);
            throw new \RuntimeException(__('payments.stripe_init_failed'));
        }

        // Inutile de créer un PaymentIntent que la Payment Sheet ne pourra pas ouvrir.
        // Le détail technique reste dans le log ; l'utilisateur reçoit un message neutre.
        if ($problem = $this->keysMismatch()) {
            Log::error('[StripeService] Paiement carte impossible : ' . $problem, ['mode' => $this->mode]);
            throw new \RuntimeException(__('payments.card_temporarily_unavailable'));
        }

        $intent = $this->client()->paymentIntents->create([
            'amount' => $minor,
            'currency' => $currency,
            'metadata' => $metadata,
            'payment_method_types' => ['card'],
        ]);

        return [
            'id' => $intent->id,
            'client_secret' => $intent->client_secret,
            'amount_minor' => $minor,
            'currency' => $currency,
            'publishable_key' => $this->publishableKey,
        ];
    }

    /** Devises sans décimales pour Stripe : le montant s'exprime en unités entières. */
    private const ZERO_DECIMAL_CURRENCIES = ['bif', 'clp', 'djf', 'gnf', 'jpy', 'kmf', 'krw', 'mga', 'pyg', 'rwf', 'ugx', 'vnd', 'vuv', 'xaf', 'xof', 'xpf'];

    /** Montant en plus petite unité Stripe (centimes, ou unités pour XAF/XOF…). */
    public static function toMinorUnits(float $amount, string $currency): int
    {
        return in_array(strtolower($currency), self::ZERO_DECIMAL_CURRENCIES, true)
            ? (int) round($amount)
            : (int) round($amount * 100);
    }

    /**
     * Statut d'un PaymentIntent : requires_payment_method | requires_confirmation |
     * processing | succeeded | canceled | requires_action.
     *
     * @return array { id, status, amount_minor, currency }
     */
    public function retrievePaymentIntent(string $id): array
    {
        $intent = $this->client()->paymentIntents->retrieve($id, []);

        return [
            'id' => $intent->id,
            'status' => $intent->status,
            'amount_minor' => $intent->amount,
            'currency' => $intent->currency,
        ];
    }

    /**
     * Bloc `business_profile` : exigé par Stripe pour activer les capabilities.
     * Ce sont des informations de la PLATEFORME, pas du vendeur — configurables via
     * les settings `stripe_business_mcc` / `stripe_business_url`.
     */
    private function businessProfileParams(): array
    {
        $url = (string) \App\Models\Setting::get('stripe_business_url', config('app.url'));
        $mcc = \App\Models\Setting::get('stripe_business_mcc', '5399');

        $params = ['mcc' => $mcc ?: null];

        // Stripe rejette (« Not a valid URL ») toute adresse non publique : en local
        // ou tant que le site n'est pas en ligne, on décrit l'activité à la place —
        // Stripe accepte `product_description` en substitut de `url`.
        if ($this->isPubliclyReachableUrl($url)) {
            $params['url'] = $url;
        } else {
            $params['product_description'] = (string) \App\Models\Setting::get(
                'stripe_business_description',
                'Place de marché en ligne : vente de produits et services entre membres.',
            );
        }

        return array_filter($params);
    }

    /**
     * Une URL réellement joignable par Stripe : domaine public résolvable depuis
     * Internet. Rejette les IP (privées comme publiques : Stripe exige un domaine
     * pour les webhooks) et les noms locaux — une adresse `192.168.x.x` est acceptée
     * à la création par Stripe mais aucune livraison n'aboutira jamais.
     */
    public function isPubliclyReachableUrl(string $url): bool
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        if (!in_array($scheme, ['http', 'https'], true)) {
            return false;
        }

        $host = parse_url($url, PHP_URL_HOST) ?: '';

        if ($host === '' || filter_var($host, FILTER_VALIDATE_IP)) {
            return false;
        }

        foreach (['localhost', '.local', '.test', '.internal', '.example'] as $needle) {
            if ($host === trim($needle, '.') || str_ends_with($host, $needle)) {
                return false;
            }
        }

        // Un domaine public a au moins un point (« exemple.com »).
        return str_contains($host, '.');
    }

    /**
     * Endpoints webhook déclarés sur le compte Stripe (diagnostic + idempotence de
     * la commande `stripe:webhook`). Le `secret` n'est JAMAIS relu ici : Stripe ne le
     * renvoie qu'à la création de l'endpoint.
     *
     * @return array<int, array{id:string,url:string,status:string,connect:bool,events:array}>
     */
    public function listWebhookEndpoints(): array
    {
        $endpoints = $this->withoutStripeNotices(
            fn () => $this->client()->webhookEndpoints->all(['limit' => 100])
        );

        $out = [];
        foreach ($endpoints->data as $e) {
            $out[] = [
                'id' => $e->id,
                'url' => $e->url,
                'status' => $e->status ?? 'unknown',
                // Un endpoint Connect porte l'id de l'application Connect dans
                // `application` ; nos créations portent aussi la metadata.
                'connect' => !empty($e->application) || ($e->metadata->asso_connect ?? null) === '1',
                'events' => (array) ($e->enabled_events ?? []),
            ];
        }

        return $out;
    }

    /** Supprime un endpoint webhook (URL devenue injoignable, doublon…). */
    public function deleteWebhookEndpoint(string $endpointId): bool
    {
        try {
            $this->withoutStripeNotices(fn () => $this->client()->webhookEndpoints->delete($endpointId, []));
            return true;
        } catch (\Throwable $e) {
            Log::warning('[StripeService] Suppression endpoint webhook échouée', [
                'endpoint' => $endpointId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Assure la présence de l'endpoint et des événements pour cette URL/portée.
     *
     * @return array{id:string,secret:?string,created:bool}
     */
    public function ensureWebhookEndpoint(string $url, array $events, bool $connect): array
    {
        foreach ($this->listWebhookEndpoints() as $existing) {
            if ($existing['url'] === $url && $existing['connect'] === $connect) {
                if (!in_array('*', $existing['events'], true)
                    && array_diff($events, $existing['events']) !== []) {
                    $this->withoutStripeNotices(fn () => $this->client()->webhookEndpoints->update(
                        $existing['id'],
                        ['enabled_events' => array_values(array_unique(array_merge($existing['events'], $events)))],
                    ));
                }
                return ['id' => $existing['id'], 'secret' => null, 'created' => false];
            }
        }

        $params = [
            'url' => $url,
            'enabled_events' => $events,
            'description' => $connect
                ? 'ASSO — événements Connect (virements IBAN vendeurs)'
                : 'ASSO — événements plateforme (paiements carte)',
            'metadata' => ['asso_connect' => $connect ? '1' : '0'],
        ];

        if ($connect) {
            $params['connect'] = true;
        }

        $endpoint = $this->withoutStripeNotices(
            fn () => $this->client()->webhookEndpoints->create($params)
        );

        return ['id' => $endpoint->id, 'secret' => $endpoint->secret ?? null, 'created' => true];
    }

}
