<?php

namespace App\Observers;

use App\Models\DeviceToken;
use App\Services\FirebaseMessagingService;
use Illuminate\Support\Facades\Log;

class DeviceTokenObserver
{
    /**
     * Handle the DeviceToken "created" event.
     */
    public function created(DeviceToken $deviceToken): void
    {
        // Abonner automatiquement le nouveau token au topic "all_users"
        if ($deviceToken->is_active) {
            try {
                $fcmService = new FirebaseMessagingService();
                $result = $fcmService->subscribeToTopic([$deviceToken->token], 'all_users');

                if ($result['success']) {
                    Log::info("Token {$deviceToken->id} automatiquement abonné au topic all_users");
                } else {
                    Log::warning("Échec de l'abonnement automatique du token {$deviceToken->id} au topic all_users: " . ($result['message'] ?? 'Unknown error'));
                }
            } catch (\Exception $e) {
                Log::error("Erreur lors de l'abonnement automatique du token {$deviceToken->id}: " . $e->getMessage());
            }
        }
    }

    /**
     * Handle the DeviceToken "updated" event.
     */
    public function updated(DeviceToken $deviceToken): void
    {
        // Si le token est réactivé, l'abonner au topic
        if ($deviceToken->is_active && $deviceToken->isDirty('is_active')) {
            try {
                $fcmService = new FirebaseMessagingService();
                $result = $fcmService->subscribeToTopic([$deviceToken->token], 'all_users');

                if ($result['success']) {
                    Log::info("Token {$deviceToken->id} réabonné au topic all_users après réactivation");
                }
            } catch (\Exception $e) {
                Log::error("Erreur lors du réabonnement du token {$deviceToken->id}: " . $e->getMessage());
            }
        }

        // Si le token est désactivé, le désabonner du topic
        if (!$deviceToken->is_active && $deviceToken->isDirty('is_active')) {
            try {
                $fcmService = new FirebaseMessagingService();
                $result = $fcmService->unsubscribeFromTopic([$deviceToken->token], 'all_users');

                if ($result['success']) {
                    Log::info("Token {$deviceToken->id} désabonné du topic all_users après désactivation");
                }
            } catch (\Exception $e) {
                Log::error("Erreur lors de la désabonnement du token {$deviceToken->id}: " . $e->getMessage());
            }
        }
    }

    /**
     * Handle the DeviceToken "deleted" event.
     */
    public function deleted(DeviceToken $deviceToken): void
    {
        // Désabonner le token du topic lors de la suppression
        try {
            $fcmService = new FirebaseMessagingService();
            $fcmService->unsubscribeFromTopic([$deviceToken->token], 'all_users');
            Log::info("Token {$deviceToken->id} désabonné du topic all_users après suppression");
        } catch (\Exception $e) {
            Log::error("Erreur lors de la désabonnement du token supprimé {$deviceToken->id}: " . $e->getMessage());
        }
    }

    /**
     * Handle the DeviceToken "restored" event.
     */
    public function restored(DeviceToken $deviceToken): void
    {
        // Réabonner le token au topic lors de la restauration
        if ($deviceToken->is_active) {
            try {
                $fcmService = new FirebaseMessagingService();
                $fcmService->subscribeToTopic([$deviceToken->token], 'all_users');
                Log::info("Token {$deviceToken->id} réabonné au topic all_users après restauration");
            } catch (\Exception $e) {
                Log::error("Erreur lors du réabonnement du token restauré {$deviceToken->id}: " . $e->getMessage());
            }
        }
    }

    /**
     * Handle the DeviceToken "force deleted" event.
     */
    public function forceDeleted(DeviceToken $deviceToken): void
    {
        // Même logique que pour deleted
        $this->deleted($deviceToken);
    }
}
