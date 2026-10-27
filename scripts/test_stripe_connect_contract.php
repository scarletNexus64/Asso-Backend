<?php

/**
 * Offline SDK regression checks; no request can reach Stripe.
 * Run with a PHP build providing pdo_sqlite:
 * APP_ENV=testing APP_CONFIG_CACHE=/tmp/asso-stripe-contract-config.php \
 * DB_CONNECTION=sqlite DB_DATABASE=:memory: CACHE_STORE=array \
 * php8.2 scripts/test_stripe_connect_contract.php
 */
chdir(dirname(__DIR__));
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

set_exception_handler(static function (Throwable $error): void {
    fwrite(STDERR, $error::class . ': ' . $error->getMessage() . PHP_EOL);
    exit(1);
});

if (!app()->environment('testing')
    || Illuminate\Support\Facades\DB::getDriverName() !== 'sqlite'
    || config('database.connections.sqlite.database') !== ':memory:'
    || config('cache.default') !== 'array') {
    throw new RuntimeException('Refusing to run outside an isolated in-memory test environment.');
}

Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
App\Models\ServiceConfiguration::setConfig('stripe', [
    'secret_key' => 'sk_test_contract_only',
    'publishable_key' => 'pk_test_contract_only',
], true);
App\Models\Setting::set('stripe_business_url', 'https://marketplace.example.com');

$checks = 0;
$check = static function (bool $condition, string $message) use (&$checks): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
    $checks++;
};

$http = new class implements Stripe\HttpClient\ClientInterface {
    public array $queue = [];

    public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1', $maxNetworkRetries = null)
    {
        $next = array_shift($this->queue);
        if (!$next || $method !== $next[0] || parse_url($absUrl, PHP_URL_PATH) !== $next[1]) {
            throw new RuntimeException('Unexpected SDK request: ' . $method . ' ' . parse_url($absUrl, PHP_URL_PATH));
        }
        ($next[2])($params);
        return [json_encode($next[3], JSON_THROW_ON_ERROR), $next[4] ?? 200, ['Request-Id' => 'req_offline_contract']];
    }
};
Stripe\ApiRequestor::setHttpClient($http);
$stripe = new App\Services\StripeService();
$noop = static fn (array $params) => null;
$account = ['id' => 'acct_offline', 'object' => 'v2.core.account'];
$http->queue = [
    ['post', '/v2/core/accounts', static function ($p) use ($check): void {
        $balance = $p['configuration']['recipient']['capabilities']['stripe_balance'];
        $check($balance === ['stripe_transfers' => ['requested' => true]], 'Read-only payout capability sent to Accounts v2.');
        $check($p['defaults']['profile']['business_url'] === 'https://marketplace.example.com', 'Business URL missing from v2 profile.');
    }, $account],
    ['post', '/v1/accounts/acct_offline', static function ($p) use ($check): void {
        $check($p['settings']['payouts']['schedule']['interval'] === 'manual', 'Payouts must remain manual.');
    }, ['id' => 'acct_offline', 'object' => 'account']],
    ['post', '/v1/accounts/acct_offline/external_accounts', static function ($p) use ($check): void {
        $check($p['external_account']['account_number'] === 'FR1420041010050500013M02606', 'Bank attachment changed.');
    }, ['id' => 'ba_offline', 'object' => 'bank_account', 'last4' => '2606', 'country' => 'FR']],
];
$created = $stripe->createCustomAccountWithBank([
    'country' => 'FR', 'currency' => 'eur', 'email' => 'sandbox@example.com',
    'first_name' => 'Test', 'last_name' => 'Sandbox', 'birth_date' => '1902-01-01',
    'account_holder_name' => 'Test Sandbox', 'iban' => 'FR1420041010050500013M02606',
]);
$check($created['id'] === 'acct_offline' && $created['external_last4'] === '2606', 'Account result incorrect.');
$check($http->queue === [], 'Account creation skipped an SDK operation.');

foreach (['restricted', 'active'] as $status) {
    $http->queue = [
        ['get', '/v1/accounts/acct_offline', $noop, [
            'id' => 'acct_offline', 'object' => 'account',
            'metadata' => ['asso_connect_api_version' => 'v2'],
            'payouts_enabled' => false,
            'requirements' => [
                'currently_due' => $status === 'restricted' ? ['business_profile.url'] : [],
                'past_due' => $status === 'restricted' ? ['business_profile.url'] : [],
                'disabled_reason' => $status === 'restricted' ? 'requirements.past_due' : null,
            ],
        ]],
        ['get', '/v2/core/accounts/acct_offline', $noop, $account + [
            'configuration' => ['recipient' => ['capabilities' => ['stripe_balance' => [
                'stripe_transfers' => ['status' => $status], 'payouts' => ['status' => $status],
            ]]]],
        ]],
    ];
    $state = $stripe->accountState('acct_offline');
    $check($state['ready'] === ($status === 'active'), 'Readiness does not follow v2 capabilities.');
    $check($state['requirements_due'] === ($status === 'restricted' ? ['business_profile.url'] : []), 'Requirements missing or duplicated.');
    $check($state['disabled_reason'] === ($status === 'restricted' ? 'requirements.past_due' : null), 'Restriction reason lost.');
    $check($http->queue === [], 'Status check skipped an SDK operation.');
}

$url = 'https://marketplace.example.com/api/v1/stripe/webhook';
$endpoint = [
    'id' => 'we_offline', 'object' => 'webhook_endpoint', 'url' => $url,
    'status' => 'enabled', 'connect' => true,
    'metadata' => ['asso_connect' => '1'], 'enabled_events' => ['invoice.paid'],
];
foreach ([['invoice.paid'], ['invoice.paid', 'payout.paid', 'payout.failed', 'account.updated'], ['*']] as $events) {
    $existing = array_replace($endpoint, ['enabled_events' => $events]);
    $http->queue = [['get', '/v1/webhook_endpoints', $noop, ['object' => 'list', 'data' => [$existing]]]];
    if ($events === ['invoice.paid']) {
        $http->queue[] = ['post', '/v1/webhook_endpoints/we_offline', static function ($p) use ($check): void {
            $check($p['enabled_events'] === ['invoice.paid', 'payout.paid', 'payout.failed', 'account.updated'], 'Existing webhook subscriptions were lost.');
            $check(array_keys($p) === ['enabled_events'], 'Webhook update changed more than event subscriptions.');
        }, $existing];
    }
    $result = $stripe->ensureWebhookEndpoint($url, ['payout.paid', 'payout.failed', 'account.updated'], true);
    $check($result === ['id' => 'we_offline', 'secret' => null, 'created' => false], 'Existing endpoint or signing secret replaced.');
    $check($http->queue === [], 'Webhook event repair skipped an SDK operation.');
}

// An invalid external bank must close the newly-created incomplete account.
$http->queue = [
    ['post', '/v2/core/accounts', $noop, $account],
    ['post', '/v1/accounts/acct_offline', $noop, ['id' => 'acct_offline', 'object' => 'account']],
    ['post', '/v1/accounts/acct_offline/external_accounts', $noop, [
        'error' => ['type' => 'invalid_request_error', 'message' => 'Test bank rejected', 'code' => 'invalid_bank_account_iban'],
    ], 400],
    ['post', '/v2/core/accounts/acct_offline/close', $noop, $account],
];
try {
    $stripe->createCustomAccountWithBank([
        'country' => 'FR', 'currency' => 'eur', 'first_name' => 'Test', 'last_name' => 'Sandbox',
        'birth_date' => '1902-01-01', 'account_holder_name' => 'Test', 'iban' => 'invalid',
    ]);
    throw new RuntimeException('Invalid bank unexpectedly accepted.');
} catch (Stripe\Exception\InvalidRequestException $error) {
    $check($error->getStripeCode() === 'invalid_bank_account_iban', 'Original bank error lost.');
}
$check($http->queue === [], 'Incomplete Connect account was not closed.');

echo json_encode(['checks' => $checks, 'stripe_requests' => 'offline only', 'database' => ':memory:'], JSON_PRETTY_PRINT) . PHP_EOL;
