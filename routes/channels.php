<?php

use Illuminate\Support\Facades\Broadcast;
use App\Models\Conversation;

// Default user channel
Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Conversation channel - Seuls les participants peuvent s'abonner
Broadcast::channel('conversation.{conversationId}', function ($user, $conversationId) {
    \Log::info('[BROADCAST AUTH] Conversation channel auth attempt', [
        'user_id' => $user->id,
        'conversation_id' => $conversationId,
    ]);

    $conversation = Conversation::find($conversationId);

    if (!$conversation) {
        \Log::warning('[BROADCAST AUTH] Conversation not found', [
            'conversation_id' => $conversationId,
        ]);
        return false;
    }

    // Vérifier que l'utilisateur est participant (user1 ou user2)
    $isAuthorized = $conversation->user1_id === $user->id || $conversation->user2_id === $user->id;

    \Log::info('[BROADCAST AUTH] Authorization check', [
        'user_id' => $user->id,
        'conversation_id' => $conversationId,
        'user1_id' => $conversation->user1_id,
        'user2_id' => $conversation->user2_id,
        'is_authorized' => $isAuthorized,
    ]);

    return $isAuthorized;
});

// User status channel - Pour recevoir les mises à jour de statut en ligne
Broadcast::channel('user.status.{userId}', function ($user, $userId) {
    // Tout utilisateur authentifié peut s'abonner au statut d'un autre utilisateur
    return $user->id !== null;
});
