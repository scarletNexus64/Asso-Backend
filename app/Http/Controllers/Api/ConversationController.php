<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use App\Events\MessageSent;
use App\Events\UserTyping;
use App\Events\UserOnlineStatus;
use Illuminate\Http\Request;

class ConversationController extends Controller
{
    /**
     * List user's conversations
     */
    public function index(Request $request)
    {
        $userId = $request->user()->id;

        $conversations = Conversation::with(['user1', 'user2', 'product', 'latestMessage'])
            ->where('user1_id', $userId)
            ->orWhere('user2_id', $userId)
            ->orderBy('last_message_at', 'desc')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'conversations' => $conversations->getCollection()->map(function($conv) use ($userId) {
                $otherUser = $conv->getOtherUser($userId);
                $unreadCount = $conv->messages()->where('sender_id', '!=', $userId)->where('is_read', false)->count();

                return [
                    'id' => $conv->id,
                    'other_user' => [
                        'id' => $otherUser->id,
                        'name' => $otherUser->name,
                        'avatar' => $otherUser->avatar,
                        'phone' => $otherUser->phone,
                    ],
                    'product' => $conv->product ? [
                        'id' => $conv->product->id,
                        'name' => $conv->product->name,
                        'price' => (float) $conv->product->price,
                        'image' => $conv->product->primaryImage ? asset('storage/' . $conv->product->primaryImage->image_path) : null,
                    ] : null,
                    'last_message' => $conv->latestMessage ? [
                        'message' => $conv->latestMessage->message,
                        'sender_id' => $conv->latestMessage->sender_id,
                        'is_read' => $conv->latestMessage->is_read,
                        'created_at' => $conv->latestMessage->created_at->toIso8601String(),
                    ] : null,
                    'unread_count' => $unreadCount,
                    'updated_at' => $conv->last_message_at?->toIso8601String() ?? $conv->created_at->toIso8601String(),
                ];
            }),
            'pagination' => [
                'current_page' => $conversations->currentPage(),
                'has_more' => $conversations->hasMorePages(),
                'total' => $conversations->total(),
            ],
        ]);
    }

    /**
     * Get or create a conversation with a user (optionally about a product)
     */
    public function startOrGet(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'product_id' => 'nullable|exists:products,id',
        ]);

        $userId = $request->user()->id;
        $otherUserId = $request->user_id;

        if ($userId == $otherUserId) {
            return response()->json(['success' => false, 'message' => 'Vous ne pouvez pas vous envoyer un message'], 422);
        }

        // Find existing conversation
        $conversation = Conversation::where(function($q) use ($userId, $otherUserId) {
            $q->where('user1_id', $userId)->where('user2_id', $otherUserId);
        })->orWhere(function($q) use ($userId, $otherUserId) {
            $q->where('user1_id', $otherUserId)->where('user2_id', $userId);
        });

        if ($request->product_id) {
            $conversation = $conversation->where('product_id', $request->product_id);
        }

        $conversation = $conversation->first();

        if (!$conversation) {
            $conversation = Conversation::create([
                'user1_id' => $userId,
                'user2_id' => $otherUserId,
                'product_id' => $request->product_id,
                'last_message_at' => now(),
            ]);
        }

        $conversation->load(['user1', 'user2', 'product']);
        $otherUser = $conversation->getOtherUser($userId);

        return response()->json([
            'success' => true,
            'conversation' => [
                'id' => $conversation->id,
                'other_user' => [
                    'id' => $otherUser->id,
                    'name' => $otherUser->name,
                    'avatar' => $otherUser->avatar,
                    'phone' => $otherUser->phone,
                ],
                'product' => $conversation->product ? [
                    'id' => $conversation->product->id,
                    'name' => $conversation->product->name,
                ] : null,
            ],
        ]);
    }

    /**
     * Get messages in a conversation
     */
    public function messages(Request $request, $conversationId)
    {
        $userId = $request->user()->id;

        $conversation = Conversation::where(function($q) use ($userId) {
            $q->where('user1_id', $userId)->orWhere('user2_id', $userId);
        })->findOrFail($conversationId);

        // Mark messages from other user as read
        Message::where('conversation_id', $conversationId)
            ->where('sender_id', '!=', $userId)
            ->where('is_read', false)
            ->update(['is_read' => true, 'read_at' => now()]);

        $messages = Message::with(['sender', 'product.primaryImage', 'product.images'])
            ->where('conversation_id', $conversationId)
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 50));

        return response()->json([
            'success' => true,
            'messages' => $messages->getCollection()->map(fn($msg) => [
                'id' => $msg->id,
                'sender_id' => $msg->sender_id,
                'message' => $msg->message,
                'product_id' => $msg->product_id,
                'product' => $msg->product ? [
                    'id' => $msg->product->id,
                    'name' => $msg->product->name,
                    'price' => (float) $msg->product->price,
                    'primary_image' => $msg->product->primaryImage ?
                        asset('storage/' . $msg->product->primaryImage->image_path) : null,
                    'image' => $msg->product->primaryImage ?
                        asset('storage/' . $msg->product->primaryImage->image_path) : null,
                    'images' => $msg->product->images ?
                        $msg->product->images->map(fn($img) => asset('storage/' . $img->image_path))->toArray() : [],
                ] : null,
                'is_read' => $msg->is_read,
                'is_mine' => $msg->sender_id == $userId,
                'created_at' => $msg->created_at->toIso8601String(),
            ]),
            'pagination' => [
                'current_page' => $messages->currentPage(),
                'has_more' => $messages->hasMorePages(),
            ],
        ]);
    }

    /**
     * Send a message in a conversation
     */
    public function sendMessage(Request $request, $conversationId)
    {
        $request->validate([
            'message' => 'required|string|max:2000',
            'product_id' => 'nullable|exists:products,id', // Optionnel: taguer un produit
        ]);

        $userId = $request->user()->id;

        $conversation = Conversation::where(function($q) use ($userId) {
            $q->where('user1_id', $userId)->orWhere('user2_id', $userId);
        })->findOrFail($conversationId);

        $message = Message::create([
            'conversation_id' => $conversationId,
            'sender_id' => $userId,
            'message' => $request->message,
            'product_id' => $request->product_id, // Ajouter product_id
            'is_read' => false,
        ]);

        // Charger le produit avec ses images si présent
        $message->load(['product.primaryImage', 'product.images']);

        $conversation->update(['last_message_at' => now()]);

        // Broadcast message en temps réel via WebSocket
        try {
            broadcast(new MessageSent($message))->toOthers();
            \Log::info('[CHAT] Message broadcasted via WebSocket', ['message_id' => $message->id]);
        } catch (\Exception $e) {
            \Log::error('[CHAT] Failed to broadcast message', [
                'message_id' => $message->id,
                'error' => $e->getMessage(),
            ]);
            // Ne pas bloquer la réponse si le broadcast échoue
        }

        return response()->json([
            'success' => true,
            'message' => [
                'id' => $message->id,
                'sender_id' => $message->sender_id,
                'message' => $message->message,
                'product_id' => $message->product_id,
                'product' => $message->product ? [
                    'id' => $message->product->id,
                    'name' => $message->product->name,
                    'price' => (float) $message->product->price,
                    'image' => $message->product->primaryImage ?
                        asset('storage/' . $message->product->primaryImage->image_path) : null,
                ] : null,
                'is_mine' => true,
                'created_at' => $message->created_at->toIso8601String(),
            ],
        ]);
    }

    /**
     * Broadcast typing indicator
     */
    public function typing(Request $request, $conversationId)
    {
        $request->validate(['is_typing' => 'required|boolean']);

        $userId = $request->user()->id;
        $userName = $request->user()->name;

        // Vérifier que l'utilisateur fait partie de la conversation
        $conversation = Conversation::where(function($q) use ($userId) {
            $q->where('user1_id', $userId)->orWhere('user2_id', $userId);
        })->findOrFail($conversationId);

        // Broadcast typing status
        broadcast(new UserTyping(
            $conversationId,
            $userId,
            $userName,
            $request->is_typing
        ))->toOthers();

        return response()->json(['success' => true]);
    }

    /**
     * Update user online status
     */
    public function updateOnlineStatus(Request $request)
    {
        $request->validate(['is_online' => 'required|boolean']);

        $user = $request->user();
        $isOnline = $request->is_online;
        $lastSeen = $isOnline ? null : now()->toIso8601String();

        // Broadcast online status
        broadcast(new UserOnlineStatus(
            $user->id,
            $user->name,
            $isOnline,
            $lastSeen
        ))->toOthers();

        return response()->json([
            'success' => true,
            'is_online' => $isOnline,
            'last_seen' => $lastSeen,
        ]);
    }
}
