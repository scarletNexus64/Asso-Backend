<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use App\Models\ShopLocationRequest;
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
        $shop = $user->primaryShop;

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
     * Get public shop info by ID with products
     */
    public function showPublic(Request $request, $shopId)
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

        $shop->load([
            'user',
            'products' => function ($query) {
                $query->where('status', 'active')
                      ->with(['images', 'primaryImage', 'category'])
                      ->orderBy('created_at', 'desc');
            }
        ]);

        // Calculate shop stats
        $productIds = $shop->products->pluck('id');
        $averageRating = 0.0;
        $totalReviews = 0;

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

        // Statistiques (P8) : une visite de boutique, dédoublonnée par visiteur.
        app(\App\Services\ShopStatisticsService::class)->record(
            \App\Models\ShopAnalyticsEvent::SHOP_VIEW,
            $shop,
            null,
            $request->user() ?? auth('sanctum')->user(),
            $request,
            'api',
        );

        return response()->json([
            'success' => true,
            'shop' => $this->formatShopPublicWithProducts($shop),
            'stats' => [
                'products_count' => $shop->products->count(),
                'average_rating' => $averageRating,
                'reviews_count' => $totalReviews,
            ],
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

        $shop = $user->primaryShop;

        if (!$shop) {
            return response()->json([
                'success' => false,
                'message' => 'Aucune boutique trouvée',
            ], 404);
        }

        Log::info('[VENDOR-SHOP-UPDATE] Starting vendor shop update', [
            'shop_id' => $shop->id,
            'vendor_id' => $user->id,
            'request_data' => $request->except(['shop_logo']),
            'all_request_data' => $request->all(),
        ]);

        $validated = $request->validate([
            'shop_city' => 'sometimes|nullable|string|max:120',
            'shop_country' => 'sometimes|nullable|string|max:120',
            'shop_name' => 'sometimes|string|max:255',
            'shop_description' => 'sometimes|nullable|string',
            'shop_address' => 'sometimes|nullable|string',
            'shop_phone' => 'sometimes|nullable|string|max:20',
            'shop_logo' => 'sometimes|nullable|image|max:2048',
            'shop_latitude' => 'sometimes|numeric|between:-90,90',
            'shop_longitude' => 'sometimes|numeric|between:-180,180',
            'categories' => 'sometimes|nullable|array',
            'categories.*' => 'string',
        ]);

        Log::info('[VENDOR-SHOP-UPDATE] Validation completed', [
            'validated_data' => $validated,
            'categories_in_validated' => $validated['categories'] ?? 'NOT SET',
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
                Log::info('[VENDOR-SHOP-UPDATE] Updating shop name', [
                    'old_name' => $shop->name,
                    'new_name' => $validated['shop_name']
                ]);
            }
            if (isset($validated['shop_description'])) {
                $updateData['description'] = $validated['shop_description'];
            }
            if (isset($validated['shop_address'])) {
                $updateData['address'] = $validated['shop_address'];
            }
            if (array_key_exists('shop_city', $validated) || array_key_exists('shop_country', $validated)) {
                $updateData['city'] = $validated['shop_city'] ?? null;
                $updateData['country'] = $validated['shop_country'] ?? null;
            } elseif (isset($validated['shop_address'])) {
                // Ancienne version de l'app : on déduit « Ville, Pays » de l'adresse.
                [$updateData['city'], $updateData['country']] = \App\Support\LocationFormatter::parse($validated['shop_address']);
            }
            if (isset($validated['shop_phone'])) {
                $updateData['phone'] = $validated['shop_phone'];
            }
            if (isset($validated['shop_latitude'])) {
                $updateData['latitude'] = $validated['shop_latitude'];
            }
            if (isset($validated['shop_longitude'])) {
                $updateData['longitude'] = $validated['shop_longitude'];
            }
            if (isset($validated['categories'])) {
                $updateData['categories'] = $validated['categories'];
                Log::info('[VENDOR-SHOP-UPDATE] Categories to update', [
                    'old_categories' => $shop->categories,
                    'new_categories' => $validated['categories'],
                ]);
            }

            // Note: Latitude and longitude are not allowed for vendors
            // They can only be updated by admin through Admin\ShopController

            // Handle logo upload
            if ($request->hasFile('shop_logo')) {
                $logoPath = $request->file('shop_logo')->store('shops', 'public');
                $updateData['logo'] = $logoPath;
                Log::info('[VENDOR-SHOP-UPDATE] Logo uploaded', [
                    'logo_path' => $logoPath
                ]);
            }

            Log::info('[VENDOR-SHOP-UPDATE] Updating shop with data', [
                'shop_id' => $shop->id,
                'update_fields' => array_keys($updateData),
                'update_data' => $updateData,
            ]);

            $shop->update($updateData);
            $shop->refresh();

            Log::info('[VENDOR-SHOP-UPDATE] Shop updated, refreshed from DB', [
                'categories_after_update' => $shop->categories,
                'name_after_update' => $shop->name,
                'description_after_update' => $shop->description,
            ]);
            $shop->load(['user', 'products', 'verifier', 'rejector']);

            $stats = $this->calculateShopStats($shop, $user);

            Log::info('[VENDOR-SHOP-UPDATE] Shop updated successfully', [
                'shop_id' => $shop->id,
                'vendor_id' => $user->id,
            ]);

            $response = [
                'success' => true,
                'message' => 'Boutique mise à jour avec succès',
                'shop' => $this->formatShop($shop),
                'stats' => $stats,
            ];

            return response()->json($response);
        } catch (\Exception $e) {
            Log::error('[VENDOR-SHOP-UPDATE] Error updating shop', [
                'shop_id' => $shop->id,
                'vendor_id' => $user->id,
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
            'logo' => $shop->logo ? media_url($shop->logo) : null,
            'address' => $shop->address,
            'city' => $shop->city,
            'country' => $shop->country,
            'location_label' => $shop->location_label,
            'latitude' => $shop->latitude,
            'longitude' => $shop->longitude,
            'categories' => $shop->categories ?? [],
            'status' => $shop->status,
            'is_certified' => (bool) $shop->is_certified,
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
            'logo' => $shop->logo ? media_url($shop->logo) : null,
            'address' => $shop->address,
            'city' => $shop->city,
            'country' => $shop->country,
            'location_label' => $shop->location_label,
            'latitude' => $shop->latitude,
            'longitude' => $shop->longitude,
            'categories' => $shop->categories ?? [],
            'is_certified' => (bool) $shop->is_certified,
            'phone' => $shop->phone ?? $shop->user->phone,
            'products_count' => $shop->products()->count(),
        ];
    }

    /**
     * Format shop for public response with products
     */
    private function formatShopPublicWithProducts(Shop $shop): array
    {
        $products = $shop->products->map(function ($product) use ($shop) {
            // Get all images
            $images = [];

            // Add primary image first
            if ($product->primaryImage) {
                $images[] = media_url($product->primaryImage->image_path);
            }

            // Add all other images
            if ($product->images && $product->images->count() > 0) {
                foreach ($product->images as $image) {
                    $imageUrl = media_url($image->image_path);
                    if (!in_array($imageUrl, $images)) {
                        $images[] = $imageUrl;
                    }
                }
            }

            return [
                'id' => $product->id,
                'name' => $product->name,
                'slug' => $product->slug,
                'description' => $product->description,
                'price' => \App\Services\CommissionService::buyerPrice($product), // prix public
                'stock' => (int) $product->stock,
                'primary_image' => $product->primaryImage ? media_url($product->primaryImage->image_path) : null,
                'images' => $images,
                'category' => $product->category,
                'condition' => $product->condition,
                'latitude' => $product->latitude,
                'longitude' => $product->longitude,
                'location' => $shop->location_label ?? $product->address,
                'created_at' => $product->created_at->toIso8601String(),
            ];
        });

        return [
            'id' => $shop->id,
            'name' => $shop->name,
            'slug' => $shop->slug,
            'description' => $shop->description,
            'logo' => $shop->logo ? media_url($shop->logo) : null,
            'address' => $shop->address,
            'city' => $shop->city,
            'country' => $shop->country,
            'location_label' => $shop->location_label,
            'latitude' => $shop->latitude,
            'longitude' => $shop->longitude,
            'categories' => $shop->categories ?? [],
            'is_certified' => (bool) $shop->is_certified,
            'phone' => $shop->phone ?? $shop->user->phone,
            'email' => $shop->email ?? $shop->user->email,
            'products' => $products,
            'owner' => [
                'id' => $shop->user->id,
                'name' => $shop->user->first_name . ' ' . $shop->user->last_name,
                'profile_picture' => $shop->user->profile_picture ? media_url($shop->user->profile_picture) : null,
            ],
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
            'logo' => $shop->logo ? media_url($shop->logo) : null,
            'status' => $shop->status,
            'is_certified' => (bool) $shop->is_certified,
            'products_count' => $shop->products()->count(),
        ];
    }

    /**
     * Calculate shop statistics
     */
    private function calculateShopStats(Shop $shop, $user): array
    {
        // Commandes / ventes / CA / audience : même source que l'écran Statistiques.
        $counters = app(\App\Services\ShopStatisticsService::class)->quickCounters($shop);
        $ordersCount = $counters['orders'];
        $totalSales = $counters['revenue'];

        // Compter les produits du vendeur, et pas tous ceux rattachés à la
        // boutique : des fiches d'autres comptes (grossistes de l'import)
        // portent le même shop_id et gonflaient le total affiché au tableau
        // de bord, qui ne correspondait plus à la liste « Mes produits ».
        $ownProducts = $shop->products()->where('user_id', $user->id);

        $totalProducts = (clone $ownProducts)->count();
        $totalStock = (clone $ownProducts)->sum('stock');

        $averageRating = 0.0;
        $totalReviews = 0;
        $productIds = (clone $ownProducts)->pluck('id');

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
            'pending_orders' => $counters['pending_orders'],
            'sales_count' => $counters['sales_count'],
            'items_sold' => $counters['items_sold'],
            'total_visits' => $counters['visits'],
            'unique_visitors' => $counters['unique_visitors'],
            'total_product_views' => $counters['product_views'],
            'total_contacts' => $counters['contacts'],
            'visits_last_7_days' => $counters['visits_last_7_days'],
        ];
    }

    /**
     * Get vendor's location change requests
     */
    public function getLocationRequests(Request $request)
    {
        $user = $request->user();

        if (!$user->hasAnyRole(['vendeur', 'vendor'])) {
            return response()->json([
                'success' => false,
                'message' => 'Vous n\'êtes pas vendeur',
            ], 403);
        }

        $shop = $user->primaryShop;

        if (!$shop) {
            return response()->json([
                'success' => false,
                'message' => 'Aucune boutique trouvée',
            ], 404);
        }

        // Get all location requests for this shop
        $requests = ShopLocationRequest::where('shop_id', $shop->id)
            ->with('reviewer')
            ->latest()
            ->get()
            ->map(function ($request) {
                return [
                    'id' => $request->id,
                    'latitude' => $request->latitude,
                    'longitude' => $request->longitude,
                    'address' => $request->address,
                    'reason' => $request->reason,
                    'status' => $request->status,
                    'rejection_reason' => $request->rejection_reason,
                    'reviewed_by' => $request->reviewer ? [
                        'id' => $request->reviewer->id,
                        'name' => $request->reviewer->first_name . ' ' . $request->reviewer->last_name,
                    ] : null,
                    'reviewed_at' => $request->reviewed_at?->toIso8601String(),
                    'created_at' => $request->created_at->toIso8601String(),
                ];
            });

        return response()->json([
            'success' => true,
            'requests' => $requests,
            'pending_count' => ShopLocationRequest::where('shop_id', $shop->id)->pending()->count(),
        ]);
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
