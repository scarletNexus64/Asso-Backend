<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    /**
     * Apply to become a vendor
     */
    public function applyVendor(Request $request)
    {
        \Log::info('========== VENDOR APPLY START ==========');
        \Log::info('[VENDOR_APPLY] Request data:', [
            'fields' => $request->except(['shop_logo', 'avatar']),
            'has_shop_logo' => $request->hasFile('shop_logo'),
            'has_avatar' => $request->hasFile('avatar'),
        ]);

        try {
            $request->validate([
                'shop_name' => 'required|string|max:255',
                'shop_description' => 'nullable|string',
                'shop_address' => 'nullable|string',
                'shop_logo' => 'nullable|image|max:2048',
                'shop_latitude' => 'nullable|numeric',
                'shop_longitude' => 'nullable|numeric',
                'categories' => 'nullable|array',
                'categories.*' => 'string',
                'company_name' => 'nullable|string|max:255',
                'first_name' => 'nullable|string|max:255',
                'last_name' => 'nullable|string|max:255',
                'gender' => 'nullable|string|max:50',
                'account_type' => 'nullable|string|max:50',
            ]);
            \Log::info('[VENDOR_APPLY] Validation passed');
        } catch (\Illuminate\Validation\ValidationException $e) {
            \Log::error('[VENDOR_APPLY] Validation failed:', [
                'errors' => $e->errors(),
            ]);
            throw $e;
        }

        $user = $request->user();
        \Log::info('[VENDOR_APPLY] User info:', [
            'user_id' => $user->id,
            'current_role' => $user->role,
            'phone' => $user->phone,
        ]);

        if ($user->hasAnyRole(['vendeur', 'vendor'])) {
            \Log::warning('[VENDOR_APPLY] User is already a vendor', [
                'user_id' => $user->id,
                'roles' => $user->getRoles(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Vous êtes déjà vendeur',
            ], 422);
        }

        // Update user profile if fields are provided
        $userUpdates = [];

        if ($request->filled('first_name')) {
            $userUpdates['first_name'] = $request->first_name;
        }

        if ($request->filled('last_name')) {
            $userUpdates['last_name'] = $request->last_name;
        }

        if ($request->filled('gender')) {
            $userUpdates['gender'] = $request->gender;
        }

        if ($request->filled('company_name')) {
            $userUpdates['company_name'] = $request->company_name;
        } elseif ($request->filled('account_type') && $request->account_type === 'Entreprise') {
            $userUpdates['company_name'] = $request->shop_name;
        }

        \Log::info('[VENDOR_APPLY] User updates prepared:', $userUpdates);

        // Create shop
        $shopData = [
            'user_id' => $user->id,
            'name' => $request->shop_name,
            'description' => $request->shop_description,
            'address' => $request->shop_address,
            'latitude' => $request->shop_latitude,
            'longitude' => $request->shop_longitude,
            'categories' => $request->categories ?? [],
            'status' => 'pending', // Shop needs admin verification
        ];

        \Log::info('[VENDOR_APPLY] Shop data prepared:', $shopData);

        // Handle shop logo upload
        if ($request->hasFile('shop_logo')) {
            try {
                $logoPath = $request->file('shop_logo')->store('shops', 'public');
                $shopData['logo'] = $logoPath;
                \Log::info('[VENDOR_APPLY] Shop logo uploaded:', ['path' => $logoPath]);
            } catch (\Exception $e) {
                \Log::error('[VENDOR_APPLY] Shop logo upload failed:', [
                    'error' => $e->getMessage(),
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Erreur lors de l\'upload du logo',
                ], 500);
            }
        }

        // Handle avatar upload
        if ($request->hasFile('avatar')) {
            try {
                $avatarPath = $request->file('avatar')->store('avatars', 'public');
                $userUpdates['avatar'] = $avatarPath;
                \Log::info('[VENDOR_APPLY] Avatar uploaded:', ['path' => $avatarPath]);
            } catch (\Exception $e) {
                \Log::error('[VENDOR_APPLY] Avatar upload failed:', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        try {
            // Create shop
            $shop = Shop::create($shopData);
            \Log::info('[VENDOR_APPLY] Shop created successfully:', [
                'shop_id' => $shop->id,
                'shop_slug' => $shop->slug,
            ]);

            // Update user info
            if (!empty($userUpdates)) {
                $user->update($userUpdates);
            }

            // Add vendor role (keeps existing roles)
            $user->addRole('vendeur');

            \Log::info('[VENDOR_APPLY] User updated successfully:', [
                'user_id' => $user->id,
                'roles' => $user->getRoles(),
                'primary_role' => $user->role,
            ]);

            \Log::info('========== VENDOR APPLY SUCCESS ==========');

            return response()->json([
                'success' => true,
                'message' => 'Vous êtes maintenant vendeur !',
                'shop' => [
                    'id' => $shop->id,
                    'name' => $shop->name,
                    'slug' => $shop->slug,
                    'logo' => $shop->logo ? asset('storage/' . $shop->logo) : null,
                    'description' => $shop->description,
                    'address' => $shop->address,
                    'latitude' => $shop->latitude,
                    'longitude' => $shop->longitude,
                    'categories' => $shop->categories,
                    'status' => $shop->status,
                ],
                'user' => [
                    'id' => $user->id,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'name' => $user->name,
                    'role' => $user->role,
                    'roles' => $user->getRoles(),
                    'company_name' => $user->company_name,
                    'gender' => $user->gender,
                    'avatar' => $user->avatar ? asset('storage/' . $user->avatar) : null,
                ],
            ]);
        } catch (\Exception $e) {
            \Log::error('[VENDOR_APPLY] Failed to create shop or update user:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            \Log::error('========== VENDOR APPLY FAILED ==========');

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création de la boutique',
                'error' => app()->environment('local') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Apply to become a delivery person
     */
    public function applyDelivery(Request $request)
    {
        $request->validate([
            'vehicle_type' => 'nullable|string|max:50',
            'address' => 'nullable|string',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
        ]);

        $user = $request->user();

        if ($user->role === 'livreur') {
            return response()->json([
                'success' => false,
                'message' => 'Vous êtes déjà livreur',
            ], 422);
        }

        $user->update([
            'role' => 'livreur',
            'address' => $request->address ?? $user->address,
            'latitude' => $request->latitude ?? $user->latitude,
            'longitude' => $request->longitude ?? $user->longitude,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Vous êtes maintenant livreur !',
            'user' => [
                'id' => $user->id,
                'role' => $user->role,
                'address' => $user->address,
            ],
        ]);
    }

    /**
     * Get vendor dashboard data
     */
    public function vendorDashboard(Request $request)
    {
        \Log::info('========== VENDOR DASHBOARD START ==========');

        $user = $request->user();
        \Log::info('[VENDOR_DASHBOARD] Request from user:', [
            'user_id' => $user->id,
            'role' => $user->role,
            'phone' => $user->phone,
        ]);

        // Check if user is a vendor
        if (!$user->hasAnyRole(['vendeur', 'vendor'])) {
            \Log::warning('[VENDOR_DASHBOARD] Access denied - user is not a vendor:', [
                'user_id' => $user->id,
                'roles' => $user->getRoles(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Vous n\'êtes pas vendeur',
            ], 403);
        }

        try {
            $user->load(['shops.products']);
            \Log::info('[VENDOR_DASHBOARD] User shops loaded:', [
                'shops_count' => $user->shops->count(),
            ]);

            $shop = $user->shops->first();

            if (!$shop) {
                \Log::error('[VENDOR_DASHBOARD] No shop found for vendor:', [
                    'user_id' => $user->id,
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Aucune boutique trouvée',
                ], 404);
            }

            \Log::info('[VENDOR_DASHBOARD] Shop found:', [
                'shop_id' => $shop->id,
                'shop_name' => $shop->name,
            ]);

            $products = $shop->products()->with(['primaryImage', 'images'])->get();
            \Log::info('[VENDOR_DASHBOARD] Products loaded:', [
                'products_count' => $products->count(),
            ]);

            $ordersCount = \DB::table('order_items')
                ->where('seller_id', $user->id)
                ->distinct('order_id')
                ->count('order_id');

            // Calculate total sales from completed orders
            $totalSales = \DB::table('order_items')
                ->join('orders', 'order_items.order_id', '=', 'orders.id')
                ->where('order_items.seller_id', $user->id)
                ->whereIn('orders.status', ['delivered', 'completed'])
                ->sum('order_items.total_price');

            // Calculate average rating from product reviews
            $averageRating = 0.0;
            $totalReviews = 0;

            if ($products->count() > 0) {
                $productIds = $products->pluck('id');
                $reviews = \DB::table('product_reviews')
                    ->whereIn('product_id', $productIds)
                    ->get();

                if ($reviews->count() > 0) {
                    $totalReviews = $reviews->count();
                    $sumRatings = $reviews->sum('rating');
                    $averageRating = round($sumRatings / $totalReviews, 1);
                }
            }

            // Package info
            $activePackage = $user->activeVendorPackage;
            $packageInfo = null;
            if ($activePackage) {
                $activePackage->load('package');
                $packageInfo = [
                    'has_package' => true,
                    'storage_total_mb' => (float) $activePackage->storage_total_mb,
                    'storage_used_mb' => (float) $activePackage->storage_used_mb,
                    'storage_remaining_mb' => (float) $activePackage->storage_remaining_mb,
                    'storage_percentage_used' => $activePackage->storage_total_mb > 0
                        ? round(($activePackage->storage_used_mb / $activePackage->storage_total_mb) * 100, 2)
                        : 0,
                    'expires_at' => $activePackage->expires_at->toIso8601String(),
                    'days_remaining' => now()->diffInDays($activePackage->expires_at, false),
                    'package_name' => $activePackage->package->name,
                    'package_id' => $activePackage->package->id,
                ];
            } else {
                $packageInfo = ['has_package' => false];
            }

            \Log::info('[VENDOR_DASHBOARD] Stats calculated:', [
                'orders_count' => $ordersCount,
                'total_sales' => $totalSales,
                'products_count' => $products->count(),
                'reviews_count' => $totalReviews,
                'average_rating' => $averageRating,
                'has_package' => $packageInfo['has_package'],
            ]);

            $responseData = [
                'success' => true,
                'data' => [
                    'shop' => [
                        'id' => $shop->id,
                        'name' => $shop->name,
                        'slug' => $shop->slug,
                        'logo' => $shop->logo ? asset('storage/' . $shop->logo) : null,
                        'description' => $shop->description,
                        'address' => $shop->address,
                        'latitude' => $shop->latitude,
                        'longitude' => $shop->longitude,
                        'categories' => $shop->categories,
                        'status' => $shop->status,
                    ],
                    'stats' => [
                        'total_orders' => $ordersCount,
                        'total_sales' => (float) $totalSales,
                        'total_products' => $products->count(),
                        'total_reviews' => $totalReviews,
                        'rating' => $averageRating,
                    ],
                    'package' => $packageInfo,
                    'verification' => [
                        'status' => $shop->status,
                        'message' => $this->getVerificationMessage($shop->status, $shop->rejection_reason),
                    ],
                    'products' => $products->map(fn($p) => [
                        'id' => $p->id,
                        'name' => $p->name,
                        'price' => (float) $p->price,
                        'stock' => $p->stock,
                        'status' => $p->status,
                        'primary_image' => $p->primaryImage ? asset('storage/' . $p->primaryImage->image_path) : null,
                    ]),
                ],
            ];

            \Log::info('========== VENDOR DASHBOARD SUCCESS ==========');

            return response()->json($responseData);
        } catch (\Exception $e) {
            \Log::error('[VENDOR_DASHBOARD] Error loading dashboard:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            \Log::error('========== VENDOR DASHBOARD FAILED ==========');

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du chargement du tableau de bord',
                'error' => app()->environment('local') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Get delivery dashboard data
     */
    public function deliveryDashboard(Request $request)
    {
        $user = $request->user();

        $deliveries = \App\Models\Order::where('delivery_person_id', $user->id)
            ->with(['items.product.primaryImage', 'user'])
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();

        $stats = [
            'total_deliveries' => \App\Models\Order::where('delivery_person_id', $user->id)->count(),
            'completed' => \App\Models\Order::where('delivery_person_id', $user->id)->where('status', 'delivered')->count(),
            'in_progress' => \App\Models\Order::where('delivery_person_id', $user->id)->where('status', 'shipped')->count(),
            'pending' => \App\Models\Order::where('delivery_person_id', $user->id)->whereIn('status', ['confirmed', 'preparing'])->count(),
        ];

        return response()->json([
            'success' => true,
            'deliveries' => $deliveries->map(fn($order) => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status,
                'total' => (float) $order->total,
                'delivery_address' => $order->delivery_address,
                'delivery_latitude' => $order->delivery_latitude,
                'delivery_longitude' => $order->delivery_longitude,
                'customer' => [
                    'id' => $order->user->id,
                    'name' => $order->user->name,
                    'phone' => $order->user->phone,
                ],
                'items_count' => $order->items->count(),
                'created_at' => $order->created_at->toIso8601String(),
            ]),
            'stats' => $stats,
        ]);
    }

    /**
     * Get verification message based on shop status
     */
    private function getVerificationMessage(string $status, ?string $rejectionReason = null): string
    {
        return match($status) {
            'active' => 'Votre boutique est vérifiée et active',
            'pending' => 'Votre boutique est en cours de vérification par notre équipe',
            'rejected' => $rejectionReason
                ? "Votre boutique a été rejetée. Raison: {$rejectionReason}"
                : 'Votre boutique a été rejetée',
            'inactive' => 'Votre boutique est désactivée',
            default => 'Statut inconnu',
        };
    }
}
