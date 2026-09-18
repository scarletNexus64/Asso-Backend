<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopAnalyticsEvent;
use App\Services\ShopStatisticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * P8 — Statistiques boutiques côté API :
 *  - POST /v1/analytics/track   : collecte (public, invités compris)
 *  - GET  /v1/vendor/statistics : tableau de bord du vendeur
 */
class ShopStatisticsController extends Controller
{
    public function __construct(private ShopStatisticsService $stats)
    {
    }

    /** POST /v1/analytics/track {event, product_id?, shop_id?} */
    public function track(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'event' => 'required|in:' . implode(',', ShopAnalyticsEvent::TYPES),
            'product_id' => 'nullable|integer|required_if:event,' . ShopAnalyticsEvent::PRODUCT_VIEW,
            'shop_id' => 'nullable|integer',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $product = $request->filled('product_id') ? Product::find($request->integer('product_id')) : null;
        $shop = $product?->shop_id
            ? Shop::find($product->shop_id)
            : ($request->filled('shop_id') ? Shop::find($request->integer('shop_id')) : null);

        if (!$shop) {
            return response()->json(['success' => false, 'message' => 'Boutique introuvable'], 404);
        }

        // Route publique : l'utilisateur connecté est identifié s'il envoie son jeton.
        $user = $request->user() ?? auth('sanctum')->user();
        $recorded = $this->stats->record($request->input('event'), $shop, $product, $user, $request);

        return response()->json(['success' => true, 'recorded' => $recorded]);
    }

    /** GET /v1/vendor/statistics?period=7d|30d|90d|365d|all */
    public function vendor(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasAnyRole(['vendeur', 'vendor'])) {
            return response()->json(['success' => false, 'message' => 'Vous n\'êtes pas vendeur'], 403);
        }

        $shop = $user->shops()->first();
        if (!$shop) {
            return response()->json(['success' => false, 'message' => 'Aucune boutique trouvée'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => array_merge(
                ['shop' => ['id' => $shop->id, 'name' => $shop->name]],
                $this->stats->shopSummary($shop, $request->query('period', '30d')),
            ),
        ]);
    }
}
