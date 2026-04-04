<?php

/**
 * Script de test pour envoyer une notification FCM
 *
 * Usage: php test_notification.php [user_id]
 * Exemple: php test_notification.php 1
 */

require __DIR__ . '/vendor/autoload.php';

use Illuminate\Support\Facades\Artisan;

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Récupérer l'ID utilisateur depuis les arguments
$userId = $argv[1] ?? null;

if (!$userId) {
    echo "❌ Usage: php test_notification.php [user_id]\n";
    echo "Exemple: php test_notification.php 1\n";
    exit(1);
}

// Trouver l'utilisateur
$user = App\Models\User::find($userId);

if (!$user) {
    echo "❌ Utilisateur avec ID {$userId} non trouvé\n";
    exit(1);
}

echo "📱 Envoi d'une notification de test à {$user->name} (ID: {$user->id})\n";

// Utiliser le service FCM
$fcmService = app(App\Services\FirebaseMessagingService::class);

$result = $fcmService->sendToUser(
    $user,
    '🔔 Test Notification',
    'Ceci est une notification de test pour vérifier que tout fonctionne correctement !',
    [
        'type' => 'test',
        'timestamp' => now()->toIso8601String(),
        'test_data' => 'Hello from test script!',
    ]
);

// Afficher le résultat
echo "\n";
if ($result['success']) {
    echo "✅ Notification envoyée avec succès !\n";
    if (isset($result['success_count'])) {
        echo "   └─ Envois réussis: {$result['success_count']}\n";
    }
    if (isset($result['failure_count'])) {
        echo "   └─ Échecs: {$result['failure_count']}\n";
    }
} else {
    echo "❌ Erreur lors de l'envoi:\n";
    echo "   └─ {$result['message']}\n";
    if (isset($result['error'])) {
        echo "   └─ Détails: {$result['error']}\n";
    }
}

// Vérifier que la notification est bien dans la BDD
$notification = App\Models\Notification::where('user_id', $userId)
    ->latest()
    ->first();

echo "\n";
if ($notification) {
    echo "✅ Notification enregistrée dans la base de données !\n";
    echo "   └─ ID: {$notification->id}\n";
    echo "   └─ Titre: {$notification->title}\n";
    echo "   └─ Créée le: {$notification->created_at}\n";
    echo "   └─ Lue: " . ($notification->is_read ? 'Oui' : 'Non') . "\n";
} else {
    echo "⚠️  Aucune notification trouvée dans la BDD\n";
}

echo "\n";
echo "📊 Statistiques de l'utilisateur:\n";
$totalNotifications = App\Models\Notification::where('user_id', $userId)->count();
$unreadNotifications = App\Models\Notification::where('user_id', $userId)->unread()->count();
echo "   └─ Total notifications: {$totalNotifications}\n";
echo "   └─ Non lues: {$unreadNotifications}\n";

echo "\n✅ Test terminé !\n";
