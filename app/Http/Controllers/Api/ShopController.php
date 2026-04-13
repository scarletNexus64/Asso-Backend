<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ShopController extends Controller
{
    /**
     * Get the authenticated vendor's shop
     */
    public function show(Request $request)
    {
        $user = $request->user();

        // Check if user is a vendor
        if (!$user->hasAnyRole(['vendeur', 'vendor'])) {
            return response()->json([
                'success' => false,
                'message' => 'Vous n\'êtes pas vendeur',
            ], 403);
        }

        // Get the user's first shop
        $shop = $user->shops()->first();

        if (!$shop) {
            return response()->json([
                'success' => false,
                'message' => 'Aucune boutique trouvée',
            ], 404);
        }

        // Load relations
        $shop->load(['user', 'products', 'verifier', 'rejector']);

        // Calculate stats
        $stats = $this->calculateShopStats($shop, $user);

        // Get vendor package info
        $packageInfo = $this->getVendorPackageInfo($user);

        return response()->json([
            'success' => true,
            'shop' => $this->formatShop($shop),
            'stats' => $stats,
            'verification' => [
                'status' => $shop->status,
                'is_verified' => $shop->isVerified(),
                'is_pending' => $shop->isPending(),
                'is_rejected' => $shop->isRejected(),
                'verified_at' => $shop->verified_at?->toIso8601String(),
                'rejected_at' => $shop->rejected_at?->toIso8601String(),
                'rejection_reason' => $shop->rejection_reason,
            ],
            'certification' => [
                'is_certified' => $shop->isCertified(),
                'certified_at' => $shop->certified_at?->toIso8601String(),
                'certification_expires_at' => $shop->certification_expires_at?->toIso8601String(),
                'is_expiring_soon' => $shop->isCertificationExpiringSoon(),
                'days_until_expiry' => $shop->daysUntilCertificationExpiry(),
            ],
            'package' => $packageInfo,
        ]);
    }

    /**
     * Get public shop info by ID
     */
    public function showPublic($shopId)
    {
        $shop = Shop::find($shopId);

        if (!$shop) {
            return response()->json([
                'success' => false,
                'message' => 'Boutique non trouvée',
            ], 404);
        }

        // Only show verified shops publicly
        if (!$shop->isVerified()) {
            return response()->json([
                'success' => false,
                'message' => 'Cette boutique n\'est pas disponible',
            ], 403);
        }

        $shop->load('user', 'products');

        return response()->json([
            'success' => true,
            'shop' => $this->formatShopPublic($shop)
        ]);
    }

    /**
     * List all vendor's shops
     */
    public function index(Request $request)
    {
        $user = $request->user();

        if (!$user->hasAnyRole(['vendeur', 'vendor'])) {
            return response()->json([
                'success' => false,
                'message' => 'Vous n\'êtes pas vendeur',
            ], 403);
        }

        $shops = $user->shops()->get();

        return response()->json([
            'success' => true,
            'shops' => $shops->map(fn($shop) => $this->formatShopBasic($shop)),
            'count' => $shops->count(),
        ]);
    }

    /**
     * Update vendor's shop
     */
    public function update(Request $request)
    {
        $user = $request->user();

        if (!$user->hasAnyRole(['vendeur', 'vendor'])) {
            return response()->json([
                'success' => false,
                'message' => 'Vous n\'êtes pas vendeur',
            ], 403);
        }

        $shop = $user->shops()->first();

        if (!$shop) {
            return response()->json([
                'success' => false,
                'message' => 'Aucune boutique trouvée',
            ], 404);
        }

        // Validate input
        $validated = $request->validate([
            'shop_name' => 'sometimes|string|max:255',
            'shop_description' => 'sometimes|nullable|string',
            'shop_address' => 'sometimes|nullable|string',
            'shop_phone' => 'sometimes|nullable|string|max:20',
            'shop_logo' => 'sometimes|nullable|image|max:2048',
            'categories' => 'sometimes|nullable|array',
            'categories.*' => 'string',
            'shop_latitude' => 'sometimes|nullable|numeric|between:-90,90',
            'shop_longitude' => 'sometimes|nullable|numeric|between:-180,180',
        ]);

        // Validation supplémentaire pour l'adresse
        if (isset($validated['shop_address'])) {
            $address = $validated['shop_address'];
            // Rejeter les adresses placeholder
            if (str_contains($address, 'Chargement') ||
                str_contains($address, 'Loading') ||
                empty(trim($address))) {
                return response()->json([
                    'success' => false,
                    'message' => 'L\'adresse fournie est invalide. Veuillez réessayer.',
                ], 422);
            }
        }

        try {
            $updateData = [];

            if (isset($validated['shop_name'])) {
                $updateData['name'] = $validated['shop_name'];
            }
            if (isset($validated['shop_description'])) {
                $updateData['description'] = $validated['shop_description'];
            }
            if (isset($validated['shop_address'])) {
                $updateData['address'] = $validated['shop_address'];
            }
            if (isset($validated['shop_phone'])) {
                $updateData['phone'] = $validated['shop_phone'];
            }
            if (isset($validated['categories'])) {
                $updateData['categories'] = $validated['categories'];
            }
            if (isset($validated['shop_latitude'])) {
                $updateData['latitude'] = $validated['shop_latitude'];
            }
            if (isset($validated['shop_longitude'])) {
                $updateData['longitude'] = $validated['shop_longitude'];
            }

            // Handle logo upload
            if ($request->hasFile('shop_logo')) {
                $logoPath = $request->file('shop_logo')->store('shops', 'public');
                $updateData['logo'] = $logoPath;
            }

            $shop->update($updateData);
            $shop->refresh();
            $shop->load(['user', 'products', 'verifier', 'rejector']);

            $stats = $this->calculateShopStats($shop, $user);

            return response()->json([
                'success' => true,
                'message' => 'Boutique mise à jour avec succès',
                'shop' => $this->formatShop($shop),
                'stats' => $stats,
            ]);
        } catch (\Exception $e) {
            Log::error('[SHOP_UPDATE] Error:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour de la boutique',
                'error' => app()->environment('local') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Format shop for API response (private)
     */
    private function formatShop(Shop $shop): array
    {
        return [
            'id' => $shop->id,
            'user_id' => $shop->user_id,
            'name' => $shop->name,
            'slug' => $shop->slug,
            'description' => $shop->description,
            'logo' => $shop->logo ? asset('storage/' . $shop->logo) : null,
            'address' => $shop->address,
            'latitude' => $shop->latitude,
            'longitude' => $shop->longitude,
            'categories' => $shop->categories ?? [],
            'status' => $shop->status,
            'phone' => $shop->phone ?? $shop->user->phone,
            'email' => $shop->email ?? $shop->user->email,
            'products_count' => $shop->products()->count(),
            'created_at' => $shop->created_at->toIso8601String(),
            'updated_at' => $shop->updated_at->toIso8601String(),
        ];
    }

    /**
     * Format shop for public response
     */
    private function formatShopPublic(Shop $shop): array
    {
        return [
            'id' => $shop->id,
            'name' => $shop->name,
            'slug' => $shop->slug,
            'description' => $shop->description,
            'logo' => $shop->logo ? asset('storage/' . $shop->logo) : null,
            'address' => $shop->address,
            'latitude' => $shop->latitude,
            'longitude' => $shop->longitude,
            'categories' => $shop->categories ?? [],
            'phone' => $shop->phone ?? $shop->user->phone,
            'products_count' => $shop->products()->count(),
        ];
    }

    /**
     * Format shop basic info
     */
    private function formatShopBasic(Shop $shop): array
    {
        return [
            'id' => $shop->id,
            'name' => $shop->name,
            'slug' => $shop->slug,
            'logo' => $shop->logo ? asset('storage/' . $shop->logo) : null,
            'status' => $shop->status,
            'products_count' => $shop->products()->count(),
        ];
    }

    /**
     * Calculate shop statistics
     */
    private function calculateShopStats(Shop $shop, $user): array
    {
        $ordersCount = DB::table('order_items')
            ->where('seller_id', $user->id)
            ->distinct('order_id')
            ->count('order_id');

        $totalSales = DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->where('order_items.seller_id', $user->id)
            ->whereIn('orders.status', ['delivered', 'completed'])
            ->sum('order_items.total_price');

        $totalProducts = $shop->products()->count();
        $totalStock = $shop->products()->sum('stock');

        $averageRating = 0.0;
        $totalReviews = 0;
        $productIds = $shop->products()->pluck('id');

        if ($productIds->count() > 0) {
            $reviews = DB::table('product_reviews')
                ->whereIn('product_id', $productIds)
                ->get();

            if ($reviews->count() > 0) {
                $totalReviews = $reviews->count();
                $sumRatings = $reviews->sum('rating');
                $averageRating = round($sumRatings / $totalReviews, 1);
            }
        }

        return [
            'total_products' => $totalProducts,
            'total_stock' => $totalStock,
            'total_orders' => (int) $ordersCount,
            'total_sales' => (float) $totalSales,
            'average_rating' => $averageRating,
            'total_reviews' => $totalReviews,
        ];
    }

    /**
     * Get vendor package information
     */
    private function getVendorPackageInfo($user): array
    {
        $vendorPackage = $user->vendorPackages()->active()->first();

        if (!$vendorPackage) {
            return [
                'has_package' => false,
                'storage_total_mb' => 0,
                'storage_used_mb' => 0,
                'storage_remaining_mb' => 0,
                'storage_total_gb' => 0.0,
                'storage_used_gb' => 0.0,
                'storage_remaining_gb' => 0.0,
                'expires_at' => null,
                'is_active' => false,
            ];
        }

        return [
            'has_package' => true,
            'storage_total_mb' => (float) $vendorPackage->storage_total_mb,
            'storage_used_mb' => (float) $vendorPackage->storage_used_mb,
            'storage_remaining_mb' => (float) $vendorPackage->storage_remaining_mb,
            'storage_total_gb' => round($vendorPackage->storage_total_mb / 1024, 2),
            'storage_used_gb' => round($vendorPackage->storage_used_mb / 1024, 2),
            'storage_remaining_gb' => round($vendorPackage->storage_remaining_mb / 1024, 2),
            'expires_at' => $vendorPackage->expires_at?->toIso8601String(),
            'is_active' => $vendorPackage->isActive(),
            'package_name' => $vendorPackage->custom_name ?? $vendorPackage->package?->name,
        ];
    }
}
