<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\DelivererSyncCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class DelivererSyncController extends Controller
{
    /**
     * Verify and sync deliverer profile using sync code
     * User must be authenticated (via Sanctum)
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function syncProfile(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'sync_code' => 'required|string|size:14', // XXXX-XXXX-XXXX = 14 chars
            'device_token' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Données de validation invalides',
                'errors' => $validator->errors()
            ], 422);
        }

        // Get authenticated user
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Utilisateur non authentifié'
            ], 401);
        }

        // Find the sync code (by code only, not user_id since it's not linked yet)
        $syncCode = DelivererSyncCode::with('company.deliveryZones.pricelist')
            ->where('sync_code', $request->sync_code)
            ->first();

        if (!$syncCode) {
            return response()->json([
                'success' => false,
                'message' => 'Code de synchronisation invalide ou non trouvé'
            ], 404);
        }

        // Check if code is already used
        if ($syncCode->is_used) {
            return response()->json([
                'success' => false,
                'message' => 'Ce code de synchronisation a déjà été utilisé',
                'used_at' => $syncCode->used_at,
                'used_by' => $syncCode->user_id
            ], 400);
        }

        // Check if code is expired
        if ($syncCode->isExpired()) {
            return response()->json([
                'success' => false,
                'message' => 'Ce code de synchronisation a expiré',
                'expired_at' => $syncCode->expires_at
            ], 400);
        }

        // Get the company
        $company = $syncCode->company;

        if (!$company) {
            return response()->json([
                'success' => false,
                'message' => 'Entreprise de livraison non trouvée pour ce code'
            ], 404);
        }

        // Link the user to the company
        $company->update(['user_id' => $user->id]);

        // Update user role to add 'livreur' if not already present
        $roles = $user->roles ?? [];
        if (!in_array('livreur', $roles)) {
            $roles[] = 'livreur';
            $user->update([
                'role' => 'livreur', // Primary role
                'roles' => $roles
            ]);
        }

        // Mark sync code as used and link to user
        $syncCode->update([
            'user_id' => $user->id,
            'is_used' => true,
            'used_at' => now(),
        ]);

        // Add device token if provided
        if ($request->device_token) {
            $user->deviceTokens()->updateOrCreate(
                ['token' => $request->device_token],
                ['device_type' => $request->device_type ?? 'mobile']
            );
        }

        // Reload user with company data
        $user->load(['delivererCompany.deliveryZones.pricelist']);

        return response()->json([
            'success' => true,
            'message' => 'Profil synchronisé avec succès! Vous êtes maintenant livreur pour ' . $company->name,
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'role' => $user->role,
                    'roles' => $user->roles,
                ],
                'company' => [
                    'id' => $company->id,
                    'name' => $company->name,
                    'phone' => $company->phone,
                    'email' => $company->email,
                    'description' => $company->description,
                    'logo' => $company->logo ? asset('storage/' . $company->logo) : null,
                    'is_active' => $company->is_active,
                ],
                'delivery_zones' => $company->deliveryZones->map(function ($zone) {
                    return [
                        'id' => $zone->id,
                        'name' => $zone->name,
                        'zone_data' => $zone->zone_data,
                        'center_latitude' => $zone->center_latitude,
                        'center_longitude' => $zone->center_longitude,
                        'is_active' => $zone->is_active,
                        'pricelist' => $zone->pricelist ? [
                            'id' => $zone->pricelist->id,
                            'pricing_type' => $zone->pricelist->pricing_type,
                            'pricing_data' => $zone->pricelist->pricing_data,
                            'is_active' => $zone->pricelist->is_active,
                        ] : null,
                    ];
                }),
                'synced_at' => now(),
            ]
        ]);
    }

    /**
     * Verify if a sync code is valid
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function verifySyncCode(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'sync_code' => 'required|string|size:14',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Données de validation invalides',
                'errors' => $validator->errors()
            ], 422);
        }

        $syncCode = DelivererSyncCode::where('sync_code', $request->sync_code)->first();

        if (!$syncCode) {
            return response()->json([
                'success' => false,
                'message' => 'Code de synchronisation non trouvé',
                'is_valid' => false
            ], 404);
        }

        $isValid = $syncCode->isValid();

        return response()->json([
            'success' => true,
            'is_valid' => $isValid,
            'data' => [
                'is_used' => $syncCode->is_used,
                'is_expired' => $syncCode->isExpired(),
                'expires_at' => $syncCode->expires_at,
                'user_id' => $syncCode->user_id,
            ]
        ]);
    }

    /**
     * Regenerate sync code for a deliverer
     * (Admin function - requires authentication)
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function regenerateSyncCode(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'send_via' => 'required|in:email,sms,whatsapp,all',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Données de validation invalides',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::find($request->user_id);

        if (!$user || !$user->hasRole('livreur')) {
            return response()->json([
                'success' => false,
                'message' => 'Utilisateur non trouvé ou n\'est pas un livreur'
            ], 404);
        }

        // Generate new sync code
        $syncCode = DelivererSyncCode::generateSyncCode();
        $expiresAt = now()->addDays(30);

        $delivererSyncCode = DelivererSyncCode::create([
            'user_id' => $user->id,
            'sync_code' => $syncCode,
            'sent_via' => $request->send_via,
            'sent_at' => now(),
            'expires_at' => $expiresAt,
        ]);

        // TODO: Send sync code via email/SMS
        // $this->sendSyncCode($user, $syncCode, $request->send_via);

        return response()->json([
            'success' => true,
            'message' => 'Nouveau code de synchronisation généré avec succès',
            'data' => [
                'sync_code' => $syncCode,
                'expires_at' => $expiresAt,
                'sent_via' => $request->send_via,
            ]
        ]);
    }
}
