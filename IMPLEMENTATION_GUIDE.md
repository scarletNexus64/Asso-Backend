# GUIDE D'IMPLÉMENTATION: APIs DE GESTION DE BOUTIQUES

## Table des matières
1. [APIs à créer (Priority 1)](#priority-1)
2. [Migrations de base de données](#migrations)
3. [Routes à ajouter](#routes)
4. [Exemples de code complet](#code)

---

## PRIORITY 1: APIs ESSENTIELLES

### 1. GET /api/v1/vendor/shop
Récupère TOUTES les infos de la boutique du vendeur connecté.

#### Fichier: `app/Http/Controllers/Api/ShopController.php`

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use Illuminate\Http\Request;

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
            ]
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
        
        // Only show active shops publicly (or verified ones)
        if ($shop->status !== 'active' || !$shop->isVerified()) {
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
            \Log::error('[SHOP_UPDATE] Error:', [
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
        $ordersCount = \DB::table('order_items')
            ->where('seller_id', $user->id)
            ->distinct('order_id')
            ->count('order_id');
        
        $totalSales = \DB::table('order_items')
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
            $reviews = \DB::table('product_reviews')
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
}
```

---

## Routes à ajouter dans `routes/api.php`

```php
// Après les vendor routes existantes
Route::prefix('v1/vendor')->middleware('auth:sanctum')->group(function () {
    // Existing routes...
    Route::get('/shop', [ShopController::class, 'show']);           // GET /api/v1/vendor/shop
    Route::put('/shop', [ShopController::class, 'update']);         // PUT /api/v1/vendor/shop
    Route::get('/shops', [ShopController::class, 'index']);         // GET /api/v1/vendor/shops
});

// Public shop routes (no auth required)
Route::prefix('v1/shops')->group(function () {
    Route::get('/{shopId}', [ShopController::class, 'showPublic']); // GET /api/v1/shops/{id}
});
```

---

## Migrations à créer

### Migration 1: Ajouter phone et email à shops table

Fichier: `database/migrations/YYYY_MM_DD_HHMMSS_add_phone_email_to_shops_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->string('phone')->nullable()->after('address');
            $table->string('email')->nullable()->after('phone');
        });
    }

    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->dropColumn(['phone', 'email']);
        });
    }
};
```

### Migration 2: Créer shop_statistics table (Optional mais recommandé)

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_statistics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->onDelete('cascade');
            $table->integer('visits_count')->default(0);
            $table->integer('clicks_count')->default(0);
            $table->integer('favorites_count')->default(0);
            $table->timestamp('date')->useCurrent();
            $table->timestamps();
            
            $table->unique(['shop_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_statistics');
    }
};
```

---

## Mise à jour du Shop Model

Ajouter à `app/Models/Shop.php`:

```php
// Ajouter à $fillable
protected $fillable = [
    'user_id',
    'name',
    'slug',
    'description',
    'categories',
    'logo',
    'shop_link',
    'address',
    'phone',    // NEW
    'email',    // NEW
    'latitude',
    'longitude',
    'status',
    'verified_at',
    'verified_by',
    'rejection_reason',
    'rejected_at',
    'rejected_by',
];

// Ajouter la relation
public function statistics(): HasMany
{
    return $this->hasMany(ShopStatistic::class);
}
```

---

## Vérification des APIs

### Test 1: Récupérer sa boutique
```bash
curl -X GET http://localhost:8000/api/v1/vendor/shop \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Accept: application/json"
```

### Test 2: Mettre à jour sa boutique
```bash
curl -X PUT http://localhost:8000/api/v1/vendor/shop \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "shop_name": "Ma Nouvelle Boutique",
    "shop_description": "Description mise à jour",
    "shop_address": "Nouvelle adresse",
    "shop_phone": "+237123456789"
  }'
```

### Test 3: Voir les infos publiques d'une boutique
```bash
curl -X GET http://localhost:8000/api/v1/shops/1 \
  -H "Accept: application/json"
```

---

## Prochaines étapes (Priority 2)

1. Créer `GET /api/v1/vendor/shop/stats` pour les statistiques détaillées
2. Implémenter le suivi des visites et clics (via trackVisit/trackClick)
3. Ajouter système de certification complet
4. Créer endpoints pour les reviews/ratings par boutique

