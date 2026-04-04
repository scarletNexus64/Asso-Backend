<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\FirebaseMessagingService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class NotificationController extends Controller
{
    protected $fcmService;

    public function __construct(FirebaseMessagingService $fcmService)
    {
        $this->fcmService = $fcmService;
    }

    /**
     * Envoyer une notification à un utilisateur spécifique
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function sendToUser(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'title' => 'required|string|max:255',
            'body' => 'required|string',
            'data' => 'nullable|array',
            'platform' => 'nullable|in:android,ios,web,macos,windows',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = User::find($request->user_id);

        $result = $this->fcmService->sendToUser(
            $user,
            $request->title,
            $request->body,
            $request->data ?? [],
            $request->platform
        );

        return response()->json($result);
    }

    /**
     * Envoyer une notification à plusieurs utilisateurs
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function sendToUsers(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_ids' => 'required|array',
            'user_ids.*' => 'exists:users,id',
            'title' => 'required|string|max:255',
            'body' => 'required|string',
            'data' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $result = $this->fcmService->sendToUsers(
            $request->user_ids,
            $request->title,
            $request->body,
            $request->data ?? []
        );

        return response()->json($result);
    }

    /**
     * Envoyer une notification à tous les utilisateurs
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function sendToAll(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'body' => 'required|string',
            'data' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $result = $this->fcmService->sendToAll(
            $request->title,
            $request->body,
            $request->data ?? []
        );

        return response()->json($result);
    }

    /**
     * Envoyer une notification à un topic
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function sendToTopic(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'topic' => 'required|string',
            'title' => 'required|string|max:255',
            'body' => 'required|string',
            'data' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $result = $this->fcmService->sendToTopic(
            $request->topic,
            $request->title,
            $request->body,
            $request->data ?? []
        );

        return response()->json($result);
    }

    /**
     * Envoyer une notification de test à l'utilisateur authentifié
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function sendTestNotification(Request $request): JsonResponse
    {
        $user = $request->user();

        $result = $this->fcmService->sendToUser(
            $user,
            '🔔 Test Notification',
            'Ceci est une notification de test depuis Asso!',
            [
                'type' => 'test',
                'timestamp' => now()->toIso8601String(),
            ]
        );

        return response()->json($result);
    }
}
