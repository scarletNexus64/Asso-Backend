<?php
/**
 * Script de test pour l'API Wallet
 * Usage: php test_wallet_api.php
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;
use App\Services\WalletService;

echo "═══════════════════════════════════════════════════════\n";
echo "   TEST API WALLET - Utilisateur ID: 50\n";
echo "═══════════════════════════════════════════════════════\n\n";

// Récupérer l'utilisateur
$user = User::find(50);

if (!$user) {
    echo "❌ Utilisateur 50 non trouvé\n";
    exit(1);
}

echo "👤 Utilisateur: {$user->first_name} {$user->last_name}\n";
echo "📧 Email: {$user->email}\n";
echo "📱 Téléphone: {$user->phone}\n\n";

// Tester WalletService
$walletService = app(WalletService::class);
$stats = $walletService->getWalletStats($user);

echo "═══════════════════════════════════════════════════════\n";
echo "   SOLDES WALLET\n";
echo "═══════════════════════════════════════════════════════\n";
echo "💰 FreeMoPay Balance: " . number_format($stats['freemopay_balance'], 0, ',', ' ') . " FCFA\n";
echo "💳 PayPal Balance: " . number_format($stats['paypal_balance'], 0, ',', ' ') . " FCFA\n";
echo "💵 Total Balance: " . number_format($stats['current_balance'], 0, ',', ' ') . " FCFA\n\n";

echo "═══════════════════════════════════════════════════════\n";
echo "   STATISTIQUES\n";
echo "═══════════════════════════════════════════════════════\n";
echo "📈 Total Credits: " . number_format($stats['total_credits'], 0, ',', ' ') . " FCFA\n";
echo "📉 Total Debits: " . number_format($stats['total_debits'], 0, ',', ' ') . " FCFA\n";
echo "📊 Total Transactions: {$stats['total_transactions']}\n\n";

// Historique des transactions
echo "═══════════════════════════════════════════════════════\n";
echo "   HISTORIQUE DES TRANSACTIONS\n";
echo "═══════════════════════════════════════════════════════\n";

$transactions = $walletService->getTransactionHistory($user, 10);

foreach ($transactions as $tx) {
    $icon = $tx->type === 'credit' ? '➕' : '➖';
    $color = $tx->type === 'credit' ? '+' : '-';
    $status = $tx->status === 'completed' ? '✅' : ($tx->status === 'pending' ? '⏳' : '❌');

    echo "{$icon} [{$status}] {$color}{$tx->amount} FCFA - {$tx->description}\n";
    echo "   Balance: {$tx->balance_before} → {$tx->balance_after} FCFA\n";
    echo "   Provider: {$tx->provider} | Date: {$tx->created_at->format('Y-m-d H:i:s')}\n\n";
}

echo "═══════════════════════════════════════════════════════\n";
echo "   TEST API ENDPOINT\n";
echo "═══════════════════════════════════════════════════════\n";

// Simuler un appel API
try {
    $controller = app(\App\Http\Controllers\Api\WalletController::class);
    $request = \Illuminate\Http\Request::create('/api/v1/wallet', 'GET');
    $request->setUserResolver(function () use ($user) {
        return $user;
    });

    $response = $controller->index($request);
    $data = $response->getData(true);

    if ($data['success']) {
        echo "✅ API Response: SUCCESS\n";
        echo "📦 Data:\n";
        echo json_encode($data['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    } else {
        echo "❌ API Response: FAILED\n";
        echo "Error: {$data['message']}\n";
    }
} catch (\Exception $e) {
    echo "❌ Exception: {$e->getMessage()}\n";
    echo "Trace: {$e->getTraceAsString()}\n";
}

echo "\n═══════════════════════════════════════════════════════\n";
echo "   TEST TERMINÉ\n";
echo "═══════════════════════════════════════════════════════\n";
