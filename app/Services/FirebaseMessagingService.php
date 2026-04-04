<?php

namespace App\Services;

use App\Models\DeviceToken;
use App\Models\User;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use Kreait\Firebase\Messaging\AndroidConfig;
use Kreait\Firebase\Messaging\ApnsConfig;
use Illuminate\Support\Facades\Log;
use Exception;

class FirebaseMessagingService
{
    protected $messaging;

    public function __construct()
    {
        try {
            // Chemin vers le fichier de configuration Firebase
            $serviceAccountPath = storage_path('app/private/firebase/service-account.json');

            if (!file_exists($serviceAccountPath)) {
                Log::warning('Firebase service account file not found at: ' . $serviceAccountPath);
                return;
            }

            $factory = (new Factory)
                ->withServiceAccount($serviceAccountPath);

            $this->messaging = $factory->createMessaging();
        } catch (Exception $e) {
            Log::error('Firebase Messaging initialization error: ' . $e->getMessage());
        }
    }

    /**
     * Envoie une notification à un utilisateur spécifique
     *
     * @param User $user
     * @param string $title
     * @param string $body
     * @param array $data
     * @param string|null $platform
     * @return array
     */
    public function sendToUser(User $user, string $title, string $body, array $data = [], ?string $platform = null): array
    {
        $tokens = $user->deviceTokens()->active();

        if ($platform) {
            $tokens = $tokens->platform($platform);
        }

        $tokens = $tokens->pluck('token')->toArray();

        if (empty($tokens)) {
            Log::info("No active tokens found for user {$user->id}");
            return [
                'success' => false,
                'message' => 'No active device tokens found for this user',
            ];
        }

        return $this->sendToTokens($tokens, $title, $body, $data);
    }

    /**
     * Envoie une notification à plusieurs utilisateurs
     *
     * @param array $userIds
     * @param string $title
     * @param string $body
     * @param array $data
     * @return array
     */
    public function sendToUsers(array $userIds, string $title, string $body, array $data = []): array
    {
        $tokens = DeviceToken::whereIn('user_id', $userIds)
            ->active()
            ->pluck('token')
            ->toArray();

        if (empty($tokens)) {
            return [
                'success' => false,
                'message' => 'No active device tokens found for these users',
            ];
        }

        return $this->sendToTokens($tokens, $title, $body, $data);
    }

    /**
     * Envoie une notification à tous les utilisateurs
     * Utilise le système de topics pour les envois en masse
     *
     * @param string $title
     * @param string $body
     * @param array $data
     * @return array
     */
    public function sendToAll(string $title, string $body, array $data = []): array
    {
        // Utiliser le topic pour les envois en masse (plus efficace)
        return $this->sendToTopic('all_users', $title, $body, $data);
    }

    /**
     * Envoie une notification à tous les utilisateurs par batch
     * Méthode de secours si le topic n'est pas configuré
     *
     * @param string $title
     * @param string $body
     * @param array $data
     * @param int $batchSize
     * @return array
     */
    public function sendToAllByBatch(string $title, string $body, array $data = [], int $batchSize = 500): array
    {
        $allTokens = DeviceToken::active()->pluck('token')->toArray();

        if (empty($allTokens)) {
            return [
                'success' => false,
                'message' => 'No active device tokens found',
            ];
        }

        $totalSuccess = 0;
        $totalFailure = 0;
        $batches = array_chunk($allTokens, $batchSize);

        foreach ($batches as $index => $batch) {
            Log::info("Sending notification batch " . ($index + 1) . "/" . count($batches));

            $result = $this->sendToTokens($batch, $title, $body, $data);

            if ($result['success']) {
                $totalSuccess += $result['success_count'] ?? 0;
                $totalFailure += $result['failure_count'] ?? 0;
            }

            // Pause courte entre les batchs pour éviter la surcharge
            if ($index < count($batches) - 1) {
                usleep(100000); // 100ms
            }
        }

        return [
            'success' => true,
            'message' => 'Batch notifications sent',
            'success_count' => $totalSuccess,
            'failure_count' => $totalFailure,
            'total_batches' => count($batches),
        ];
    }

    /**
     * Envoie une notification à un topic
     *
     * @param string $topic
     * @param string $title
     * @param string $body
     * @param array $data
     * @return array
     */
    public function sendToTopic(string $topic, string $title, string $body, array $data = []): array
    {
        if (!$this->messaging) {
            return [
                'success' => false,
                'message' => 'Firebase Messaging not initialized',
            ];
        }

        try {
            $message = CloudMessage::withTarget('topic', $topic)
                ->withNotification(Notification::create($title, $body))
                ->withData($data)
                ->withAndroidConfig(
                    AndroidConfig::fromArray([
                        'priority' => 'high',
                        'notification' => [
                            'sound' => 'default',
                            'channel_id' => 'high_importance_channel',
                        ],
                    ])
                )
                ->withApnsConfig(
                    ApnsConfig::fromArray([
                        'headers' => [
                            'apns-priority' => '10',
                        ],
                        'payload' => [
                            'aps' => [
                                'sound' => 'default',
                                'badge' => 1,
                            ],
                        ],
                    ])
                );

            $this->messaging->send($message);

            Log::info("Notification sent to topic: {$topic}");

            return [
                'success' => true,
                'message' => 'Notification sent to topic successfully',
            ];
        } catch (Exception $e) {
            Log::error('Error sending notification to topic: ' . $e->getMessage());

            return [
                'success' => false,
                'message' => 'Failed to send notification to topic',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Envoie une notification à plusieurs tokens
     *
     * @param array $tokens
     * @param string $title
     * @param string $body
     * @param array $data
     * @return array
     */
    public function sendToTokens(array $tokens, string $title, string $body, array $data = []): array
    {
        if (!$this->messaging) {
            return [
                'success' => false,
                'message' => 'Firebase Messaging not initialized',
            ];
        }

        if (empty($tokens)) {
            return [
                'success' => false,
                'message' => 'No tokens provided',
            ];
        }

        try {
            $message = CloudMessage::new()
                ->withNotification(Notification::create($title, $body))
                ->withData($data)
                ->withAndroidConfig(
                    AndroidConfig::fromArray([
                        'priority' => 'high',
                        'notification' => [
                            'sound' => 'default',
                            'channel_id' => 'high_importance_channel',
                            'color' => '#FF6B35',
                        ],
                    ])
                )
                ->withApnsConfig(
                    ApnsConfig::fromArray([
                        'headers' => [
                            'apns-priority' => '10',
                        ],
                        'payload' => [
                            'aps' => [
                                'sound' => 'default',
                                'badge' => 1,
                                'alert' => [
                                    'title' => $title,
                                    'body' => $body,
                                ],
                            ],
                        ],
                    ])
                );

            $report = $this->messaging->sendMulticast($message, $tokens);

            $successCount = $report->successes()->count();
            $failureCount = $report->failures()->count();

            // Désactiver les tokens invalides
            if ($failureCount > 0) {
                foreach ($report->failures()->getItems() as $failure) {
                    $invalidToken = $failure->target()->value();

                    // Marquer le token comme inactif
                    DeviceToken::where('token', $invalidToken)
                        ->update(['is_active' => false]);

                    Log::warning("Invalid FCM token deactivated: {$invalidToken}");
                }
            }

            Log::info("Notification sent: {$successCount} succeeded, {$failureCount} failed");

            return [
                'success' => true,
                'message' => 'Notifications sent',
                'success_count' => $successCount,
                'failure_count' => $failureCount,
            ];
        } catch (Exception $e) {
            Log::error('Error sending notifications: ' . $e->getMessage());

            return [
                'success' => false,
                'message' => 'Failed to send notifications',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Abonne des tokens à un topic
     *
     * @param array $tokens
     * @param string $topic
     * @return array
     */
    public function subscribeToTopic(array $tokens, string $topic): array
    {
        if (!$this->messaging) {
            return [
                'success' => false,
                'message' => 'Firebase Messaging not initialized',
            ];
        }

        try {
            $this->messaging->subscribeToTopic($topic, $tokens);

            Log::info("Tokens subscribed to topic: {$topic}");

            return [
                'success' => true,
                'message' => 'Tokens subscribed to topic successfully',
            ];
        } catch (Exception $e) {
            Log::error('Error subscribing to topic: ' . $e->getMessage());

            return [
                'success' => false,
                'message' => 'Failed to subscribe to topic',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Désabonne des tokens d'un topic
     *
     * @param array $tokens
     * @param string $topic
     * @return array
     */
    public function unsubscribeFromTopic(array $tokens, string $topic): array
    {
        if (!$this->messaging) {
            return [
                'success' => false,
                'message' => 'Firebase Messaging not initialized',
            ];
        }

        try {
            $this->messaging->unsubscribeFromTopic($topic, $tokens);

            Log::info("Tokens unsubscribed from topic: {$topic}");

            return [
                'success' => true,
                'message' => 'Tokens unsubscribed from topic successfully',
            ];
        } catch (Exception $e) {
            Log::error('Error unsubscribing from topic: ' . $e->getMessage());

            return [
                'success' => false,
                'message' => 'Failed to unsubscribe from topic',
                'error' => $e->getMessage(),
            ];
        }
    }
}
