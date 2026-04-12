<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Models\VendorPackage;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PackageController extends Controller
{
    protected WalletService $walletService;

    public function __construct(WalletService $walletService)
    {
        $this->walletService = $walletService;
    }

    /**
     * List all active storage packages
     */
    public function index()
    {
        $packages = Package::ofType('storage')
            ->active()
            ->ordered()
            ->get();

        return response()->json([
            'success' => true,
            'packages' => $packages->map(fn($package) => [
                'id' => $package->id,
                'name' => $package->name,
                'description' => $package->description,
                'price' => (float) $package->price,
                'formatted_price' => $package->formatted_price,
                'duration_days' => $package->duration_days,
                'formatted_duration' => $package->formatted_duration,
                'storage_size_mb' => $package->storage_size_mb,
                'formatted_storage_size' => $package->formatted_storage_size,
                'benefits' => $package->benefits,
                'is_popular' => (bool) $package->is_popular,
            ]),
        ]);
    }

    /**
     * Subscribe to a package
     */
    public function subscribe(Request $request)
    {
        Log::info("╔════════════════════════════════════════════════════════════════════╗");
        Log::info("║ [PackageController] 📦 PACKAGE SUBSCRIPTION REQUEST               ║");
        Log::info("╚════════════════════════════════════════════════════════════════════╝");

        $validated = $request->validate([
            'package_id' => 'required|exists:packages,id',
            'wallet_type' => 'required|in:freemopay,paypal',
        ]);

        $user = $request->user();
        $package = Package::findOrFail($validated['package_id']);
        $walletType = $validated['wallet_type'];

        Log::info("[PackageController] 📝 Request details", [
            'user_id' => $user->id,
            'package_id' => $package->id,
            'package_name' => $package->name,
            'package_price' => $package->price,
            'wallet_type' => $walletType,
        ]);

        // Verify package is storage type
        if ($package->type !== 'storage') {
            return response()->json([
                'success' => false,
                'message' => 'Ce package n\'est pas un package de stockage',
            ], 422);
        }

        // Verify package is active
        if (!$package->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Ce package n\'est plus disponible',
            ], 422);
        }

        // Check if user already has an active package
        $existingPackage = $user->activeVendorPackage;
        if ($existingPackage) {
            Log::warning("[PackageController] ⚠️ User already has an active package", [
                'user_id' => $user->id,
                'existing_package_id' => $existingPackage->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Vous avez déjà un package actif. Veuillez attendre son expiration avant d\'en souscrire un nouveau.',
                'existing_package' => [
                    'name' => $existingPackage->package->name,
                    'expires_at' => $existingPackage->expires_at->toIso8601String(),
                ],
            ], 422);
        }

        // Verify user has enough balance in the selected wallet
        $canPay = $this->walletService->canPayWithWallet($user, $package->price, $walletType);

        if (!$canPay['can_pay']) {
            Log::warning("[PackageController] ❌ Insufficient balance", [
                'user_id' => $user->id,
                'wallet_type' => $walletType,
                'current_balance' => $canPay['current_balance'],
                'required_amount' => $package->price,
                'missing_amount' => $canPay['missing_amount'],
            ]);

            return response()->json([
                'success' => false,
                'message' => $canPay['message'],
                'data' => [
                    'current_balance' => $canPay['current_balance'],
                    'required_amount' => $package->price,
                    'missing_amount' => $canPay['missing_amount'],
                    'wallet_type' => $walletType,
                ],
            ], 422);
        }

        try {
            DB::beginTransaction();

            Log::info("[PackageController] 💳 Debiting wallet...", [
                'wallet_type' => $walletType,
                'amount' => $package->price,
            ]);

            // Debit the wallet
            $walletTransaction = $this->walletService->debit(
                user: $user,
                amount: $package->price,
                description: "Achat de package de stockage: {$package->name}",
                referenceType: 'vendor_package',
                referenceId: null, // Will be updated after creating the vendor package
                metadata: [
                    'package_id' => $package->id,
                    'package_name' => $package->name,
                    'storage_size_mb' => $package->storage_size_mb,
                    'duration_days' => $package->duration_days,
                ],
                provider: $walletType
            );

            Log::info("[PackageController] ✅ Wallet debited successfully", [
                'wallet_transaction_id' => $walletTransaction->id,
                'balance_before' => $walletTransaction->balance_before,
                'balance_after' => $walletTransaction->balance_after,
            ]);

            // Create vendor package
            $vendorPackage = VendorPackage::create([
                'user_id' => $user->id,
                'package_id' => $package->id,
                'storage_total_mb' => $package->storage_size_mb,
                'storage_used_mb' => 0,
                'storage_remaining_mb' => $package->storage_size_mb,
                'purchased_at' => now(),
                'expires_at' => now()->addDays($package->duration_days),
                'status' => 'active',
                'payment_reference' => 'PKG-' . strtoupper(Str::random(10)),
            ]);

            // Update wallet transaction with vendor package reference
            $walletTransaction->update([
                'reference_id' => $vendorPackage->id,
            ]);

            Log::info("[PackageController] 📦 Vendor package created successfully", [
                'vendor_package_id' => $vendorPackage->id,
                'payment_reference' => $vendorPackage->payment_reference,
            ]);

            DB::commit();

            $vendorPackage->load('package');

            Log::info("╔════════════════════════════════════════════════════════════════════╗");
            Log::info("║ [PackageController] ✅ PACKAGE SUBSCRIPTION SUCCESSFUL            ║");
            Log::info("╚════════════════════════════════════════════════════════════════════╝");

            return response()->json([
                'success' => true,
                'message' => 'Package souscrit avec succès',
                'vendor_package' => [
                    'id' => $vendorPackage->id,
                    'storage_total_mb' => (float) $vendorPackage->storage_total_mb,
                    'storage_used_mb' => (float) $vendorPackage->storage_used_mb,
                    'storage_remaining_mb' => (float) $vendorPackage->storage_remaining_mb,
                    'purchased_at' => $vendorPackage->purchased_at->toIso8601String(),
                    'expires_at' => $vendorPackage->expires_at->toIso8601String(),
                    'status' => $vendorPackage->status,
                    'payment_reference' => $vendorPackage->payment_reference,
                    'wallet_type' => $walletType,
                    'package' => [
                        'id' => $vendorPackage->package->id,
                        'name' => $vendorPackage->package->name,
                        'price' => (float) $vendorPackage->package->price,
                        'formatted_price' => $vendorPackage->package->formatted_price,
                    ],
                ],
                'wallet_transaction' => [
                    'id' => $walletTransaction->id,
                    'amount' => (float) $walletTransaction->amount,
                    'balance_before' => (float) $walletTransaction->balance_before,
                    'balance_after' => (float) $walletTransaction->balance_after,
                    'provider' => $walletTransaction->provider,
                ],
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('[PackageController] ❌ Error during subscription:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la souscription au package',
                'error' => app()->environment('local') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Get current active package for the authenticated user
     */
    public function currentPackage(Request $request)
    {
        $user = $request->user();
        $vendorPackage = $user->activeVendorPackage;

        if (!$vendorPackage) {
            return response()->json([
                'success' => false,
                'message' => 'Aucun package actif',
                'has_package' => false,
            ], 404);
        }

        $vendorPackage->load('package');

        return response()->json([
            'success' => true,
            'has_package' => true,
            'vendor_package' => [
                'id' => $vendorPackage->id,
                'storage_total_mb' => (float) $vendorPackage->storage_total_mb,
                'storage_used_mb' => (float) $vendorPackage->storage_used_mb,
                'storage_remaining_mb' => (float) $vendorPackage->storage_remaining_mb,
                'storage_percentage_used' => $vendorPackage->storage_total_mb > 0
                    ? round(($vendorPackage->storage_used_mb / $vendorPackage->storage_total_mb) * 100, 2)
                    : 0,
                'purchased_at' => $vendorPackage->purchased_at->toIso8601String(),
                'expires_at' => $vendorPackage->expires_at->toIso8601String(),
                'days_remaining' => now()->diffInDays($vendorPackage->expires_at, false),
                'status' => $vendorPackage->status,
                'payment_reference' => $vendorPackage->payment_reference,
                'package' => [
                    'id' => $vendorPackage->package->id,
                    'name' => $vendorPackage->package->name,
                    'description' => $vendorPackage->package->description,
                    'price' => (float) $vendorPackage->package->price,
                    'formatted_price' => $vendorPackage->package->formatted_price,
                    'duration_days' => $vendorPackage->package->duration_days,
                    'formatted_duration' => $vendorPackage->package->formatted_duration,
                    'storage_size_mb' => $vendorPackage->package->storage_size_mb,
                    'formatted_storage_size' => $vendorPackage->package->formatted_storage_size,
                ],
            ],
        ]);
    }
}
