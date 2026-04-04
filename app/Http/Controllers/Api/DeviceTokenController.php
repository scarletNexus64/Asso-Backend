<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class DeviceTokenController extends Controller
{
    /**
     * Enregistrer ou mettre à jour un token FCM pour l'utilisateur authentifié
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|string|max:255',
            'platform' => 'required|in:android,ios,web,macos,windows',
            'device_name' => 'nullable|string|max:255',
            'device_model' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = $request->user();

        // Vérifier si le token existe déjà pour cet utilisateur
        $deviceToken = DeviceToken::where('user_id', $user->id)
            ->where('token', $request->token)
            ->first();

        if ($deviceToken) {
            // Mettre à jour le token existant
            $deviceToken->update([
                'platform' => $request->platform,
                'device_name' => $request->device_name,
                'device_model' => $request->device_model,
                'is_active' => true,
                'last_used_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Device token updated successfully',
                'data' => $deviceToken,
            ]);
        }

        // Créer un nouveau token
        $deviceToken = DeviceToken::create([
            'user_id' => $user->id,
            'token' => $request->token,
            'platform' => $request->platform,
            'device_name' => $request->device_name,
            'device_model' => $request->device_model,
            'is_active' => true,
            'last_used_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Device token registered successfully',
            'data' => $deviceToken,
        ], 201);
    }

    /**
     * Obtenir tous les tokens de l'utilisateur authentifié
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $tokens = DeviceToken::where('user_id', $user->id)
            ->orderBy('last_used_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $tokens,
        ]);
    }

    /**
     * Supprimer un token spécifique
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $deviceToken = DeviceToken::where('user_id', $user->id)
            ->where('id', $id)
            ->first();

        if (!$deviceToken) {
            return response()->json([
                'success' => false,
                'message' => 'Device token not found',
            ], 404);
        }

        $deviceToken->delete();

        return response()->json([
            'success' => true,
            'message' => 'Device token deleted successfully',
        ]);
    }

    /**
     * Supprimer un token par sa valeur
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function deleteByToken(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = $request->user();

        $deleted = DeviceToken::where('user_id', $user->id)
            ->where('token', $request->token)
            ->delete();

        if ($deleted) {
            return response()->json([
                'success' => true,
                'message' => 'Device token deleted successfully',
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Device token not found',
        ], 404);
    }

    /**
     * Désactiver un token
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function deactivate(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $deviceToken = DeviceToken::where('user_id', $user->id)
            ->where('id', $id)
            ->first();

        if (!$deviceToken) {
            return response()->json([
                'success' => false,
                'message' => 'Device token not found',
            ], 404);
        }

        $deviceToken->deactivate();

        return response()->json([
            'success' => true,
            'message' => 'Device token deactivated successfully',
            'data' => $deviceToken->fresh(),
        ]);
    }

    /**
     * Activer un token
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function activate(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $deviceToken = DeviceToken::where('user_id', $user->id)
            ->where('id', $id)
            ->first();

        if (!$deviceToken) {
            return response()->json([
                'success' => false,
                'message' => 'Device token not found',
            ], 404);
        }

        $deviceToken->activate();

        return response()->json([
            'success' => true,
            'message' => 'Device token activated successfully',
            'data' => $deviceToken->fresh(),
        ]);
    }
}
