<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Models\VendorPackage;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PackageController extends Controller
{
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
        $validated = $request->validate([
            'package_id' => 'required|exists:packages,id',
        ]);

        $user = $request->user();
        $package = Package::findOrFail($validated['package_id']);

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

        try {
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

            $vendorPackage->load('package');

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
                    'package' => [
                        'id' => $vendorPackage->package->id,
                        'name' => $vendorPackage->package->name,
                        'price' => (float) $vendorPackage->package->price,
                        'formatted_price' => $vendorPackage->package->formatted_price,
                    ],
                ],
            ], 201);
        } catch (\Exception $e) {
            \Log::error('[PACKAGE_SUBSCRIBE] Error:', [
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
