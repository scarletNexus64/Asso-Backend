<?php

/**
 * Script de diagnostic pour les problèmes de dépôts
 * Usage: php diagnose_deposits.php
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\WalletTransaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

echo "╔════════════════════════════════════════════════════════════════════╗\n";
echo "║          DIAGNOSTIC DES DÉPÔTS - FREEMOPAY WALLET                 ║\n";
echo "╚════════════════════════════════════════════════════════════════════╝\n\n";

// 1. Vérifier les migrations
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "1️⃣  VÉRIFICATION DES TABLES\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

try {
    // Vérifier que la table wallet_transactions existe
    $tableExists = DB::select("SHOW TABLES LIKE 'wallet_transactions'");
    if (!empty($tableExists)) {
        echo "✅ Table 'wallet_transactions' existe\n";

        // Vérifier les colonnes importantes
        $columns = DB::select("DESCRIBE wallet_transactions");
        $columnNames = array_column($columns, 'Field');

        $requiredColumns = ['id', 'user_id', 'type', 'amount', 'balance_before', 'balance_after', 'status', 'provider', 'metadata'];
        $missing = array_diff($requiredColumns, $columnNames);

        if (empty($missing)) {
            echo "✅ Toutes les colonnes requises sont présentes\n";
        } else {
            echo "❌ Colonnes manquantes: " . implode(', ', $missing) . "\n";
            echo "   👉 Exécutez: php artisan migrate\n";
        }
    } else {
        echo "❌ Table 'wallet_transactions' n'existe pas!\n";
        echo "   👉 Exécutez: php artisan migrate\n";
    }

    // Vérifier les colonnes wallet dans users
    $userColumns = DB::select("DESCRIBE users");
    $userColumnNames = array_column($userColumns, 'Field');

    if (in_array('freemopay_wallet_balance', $userColumnNames)) {
        echo "✅ Colonne 'freemopay_wallet_balance' existe dans users\n";
    } else {
        echo "❌ Colonne 'freemopay_wallet_balance' manquante dans users\n";
        echo "   👉 Exécutez: php artisan migrate\n";
    }

} catch (\Exception $e) {
    echo "❌ Erreur lors de la vérification des tables: " . $e->getMessage() . "\n";
}

echo "\n";

// 2. Vérifier les dépôts en pending
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "2️⃣  DÉPÔTS EN ATTENTE (PENDING)\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

try {
    $pendingDeposits = WalletTransaction::where('type', 'credit')
        ->where('status', 'pending')
        ->where('provider', 'freemopay')
        ->orderBy('created_at', 'desc')
        ->limit(10)
        ->get();

    if ($pendingDeposits->isEmpty()) {
        echo "ℹ️  Aucun dépôt en attente\n";
    } else {
        echo "📊 Trouvé " . $pendingDeposits->count() . " dépôt(s) en attente:\n\n";

        foreach ($pendingDeposits as $deposit) {
            $metadata = $deposit->metadata ?? [];
            $reference = $metadata['provider_reference'] ?? 'AUCUNE';
            $phoneNumber = $metadata['phone_number'] ?? 'N/A';
            $age = $deposit->created_at->diffForHumans();

            echo "  📝 ID: {$deposit->id}\n";
            echo "     User: {$deposit->user_id}\n";
            echo "     Montant: {$deposit->amount} FCFA\n";
            echo "     Référence FreeMoPay: {$reference}\n";
            echo "     Téléphone: {$phoneNumber}\n";
            echo "     Créé: {$age}\n";

            if ($reference === 'AUCUNE') {
                echo "     ⚠️  PAS DE RÉFÉRENCE FREEMOPAY! Le job ne peut pas vérifier ce dépôt.\n";
            }

            echo "\n";
        }
    }
} catch (\Exception $e) {
    echo "❌ Erreur: " . $e->getMessage() . "\n";
}

echo "\n";

// 3. Vérifier les dépôts complétés récemment
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "3️⃣  DÉPÔTS COMPLÉTÉS (24 DERNIÈRES HEURES)\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

try {
    $completedDeposits = WalletTransaction::where('type', 'credit')
        ->where('status', 'completed')
        ->where('provider', 'freemopay')
        ->where('created_at', '>=', now()->subHours(24))
        ->orderBy('created_at', 'desc')
        ->limit(5)
        ->get();

    if ($completedDeposits->isEmpty()) {
        echo "ℹ️  Aucun dépôt complété dans les dernières 24h\n";
    } else {
        echo "📊 Trouvé " . $completedDeposits->count() . " dépôt(s) complété(s):\n\n";

        foreach ($completedDeposits as $deposit) {
            $user = $deposit->user;
            $metadata = $deposit->metadata ?? [];
            $completedAt = $metadata['completed_at'] ?? 'N/A';

            echo "  ✅ ID: {$deposit->id}\n";
            echo "     User: {$deposit->user_id} ({$user->name})\n";
            echo "     Montant: {$deposit->amount} FCFA\n";
            echo "     Balance avant: {$deposit->balance_before} FCFA\n";
            echo "     Balance après: {$deposit->balance_after} FCFA\n";
            echo "     Complété: {$completedAt}\n";
            echo "     Solde actuel user: {$user->freemopay_wallet_balance} FCFA\n\n";
        }
    }
} catch (\Exception $e) {
    echo "❌ Erreur: " . $e->getMessage() . "\n";
}

echo "\n";

// 4. Vérifier les utilisateurs avec solde > 0
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "4️⃣  UTILISATEURS AVEC SOLDE FREEMOPAY\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

try {
    $usersWithBalance = User::where('freemopay_wallet_balance', '>', 0)
        ->orderBy('freemopay_wallet_balance', 'desc')
        ->limit(5)
        ->get();

    if ($usersWithBalance->isEmpty()) {
        echo "⚠️  Aucun utilisateur n'a de solde FreeMoPay > 0\n";
        echo "   Cela confirme que les dépôts ne créditent pas les wallets!\n";
    } else {
        echo "📊 Trouvé " . $usersWithBalance->count() . " utilisateur(s) avec solde:\n\n";

        foreach ($usersWithBalance as $user) {
            echo "  💰 {$user->name} (ID: {$user->id})\n";
            echo "     Solde: {$user->freemopay_wallet_balance} FCFA\n\n";
        }
    }
} catch (\Exception $e) {
    echo "❌ Erreur: " . $e->getMessage() . "\n";
}

echo "\n";

// 5. Vérifier les jobs Laravel
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "5️⃣  VÉRIFICATION DES JOBS/QUEUES\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

try {
    // Vérifier la table jobs
    $jobsTable = DB::select("SHOW TABLES LIKE 'jobs'");
    if (empty($jobsTable)) {
        echo "❌ Table 'jobs' n'existe pas!\n";
        echo "   👉 Exécutez: php artisan queue:table && php artisan migrate\n";
    } else {
        $pendingJobs = DB::table('jobs')->where('queue', 'deposits')->count();
        $failedJobs = DB::table('failed_jobs')->count();

        echo "📊 Jobs en attente (queue 'deposits'): {$pendingJobs}\n";
        echo "📊 Jobs échoués (total): {$failedJobs}\n";

        if ($failedJobs > 0) {
            echo "\n⚠️  Il y a des jobs échoués! Vérifiez avec:\n";
            echo "   php artisan queue:failed\n";
        }
    }
} catch (\Exception $e) {
    echo "❌ Erreur: " . $e->getMessage() . "\n";
}

echo "\n";

// 6. Vérifier la configuration FreeMoPay
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "6️⃣  CONFIGURATION FREEMOPAY\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

try {
    $config = \App\Models\ServiceConfiguration::where('service_name', 'freemopay')->first();

    if (!$config) {
        echo "❌ Configuration FreeMoPay introuvable!\n";
        echo "   👉 Ajoutez les credentials dans la table 'service_configurations'\n";
    } else {
        $keys = $config->config_keys ?? [];
        $hasAppKey = isset($keys['app_key']) && !empty($keys['app_key']);
        $hasSecretKey = isset($keys['secret_key']) && !empty($keys['secret_key']);

        echo ($hasAppKey ? "✅" : "❌") . " app_key: " . ($hasAppKey ? "Configuré" : "MANQUANT") . "\n";
        echo ($hasSecretKey ? "✅" : "❌") . " secret_key: " . ($hasSecretKey ? "Configuré" : "MANQUANT") . "\n";

        if (!$hasAppKey || !$hasSecretKey) {
            echo "\n⚠️  Les credentials FreeMoPay sont incomplets!\n";
        }
    }
} catch (\Exception $e) {
    echo "❌ Erreur: " . $e->getMessage() . "\n";
}

echo "\n";

// 7. Recommandations
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "7️⃣  RECOMMANDATIONS & PROCHAINES ÉTAPES\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

echo "Pour que les dépôts fonctionnent, vous DEVEZ avoir ces processus actifs:\n\n";

echo "1️⃣  Queue Worker (pour traiter les jobs)\n";
echo "   php artisan queue:work --queue=deposits,default\n\n";

echo "2️⃣  Scheduler (pour vérifier les dépôts toutes les 30s)\n";
echo "   php artisan schedule:work\n";
echo "   OU configurer un cron job:\n";
echo "   * * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1\n\n";

echo "3️⃣  Vérifier les logs en temps réel\n";
echo "   tail -f storage/logs/laravel.log | grep -E 'DEPOSIT|WALLET|FREEMOPAY'\n\n";

echo "4️⃣  Forcer l'exécution du job de vérification\n";
echo "   php artisan schedule:run\n\n";

echo "5️⃣  Tester manuellement un dépôt pending\n";
echo "   php artisan tinker\n";
echo "   >>> \$deposit = App\\Models\\WalletTransaction::find(ID_DU_DEPOT);\n";
echo "   >>> App\\Jobs\\Wallet\\ProcessDepositStatusJob::dispatch(\$deposit->id);\n\n";

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "FIN DU DIAGNOSTIC\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
