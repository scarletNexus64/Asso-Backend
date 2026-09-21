<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopAnalyticsEvent;
use App\Services\ShopStatisticsService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * P8 — Statistiques boutiques côté API :
 *  - POST /v1/analytics/track          : collecte (public, invités compris)
 *  - GET  /v1/vendor/statistics        : tableau de bord du vendeur
 *  - GET  /v1/vendor/statistics/export : rapport CSV ou PDF de la période
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

        $shop = $user->primaryShop;
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

    /**
     * GET /v1/vendor/statistics/export?period=30d&format=csv|pdf
     *
     * Reprend exactement le résumé affiché à l'écran : le vendeur retrouve
     * dans son rapport les chiffres qu'il vient de consulter.
     */
    public function export(Request $request): StreamedResponse|JsonResponse
    {
        $shop = $this->vendorShop($request);
        if ($shop instanceof JsonResponse) {
            return $shop;
        }

        $format = strtolower((string) $request->query('format', 'csv'));
        if (!in_array($format, ['csv', 'pdf'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Format non supporté (csv ou pdf).',
            ], 422);
        }

        $summary = $this->stats->shopSummary($shop, $request->query('period', '30d'));
        $slug = \Illuminate\Support\Str::slug($shop->name) ?: 'boutique';
        $stamp = now()->format('Y-m-d');

        return $format === 'pdf'
            ? $this->exportPdf($shop, $summary, "statistiques-{$slug}-{$stamp}.pdf")
            : $this->exportCsv($shop, $summary, "statistiques-{$slug}-{$stamp}.csv");
    }

    /** Boutique du vendeur authentifié, ou la réponse d'erreur à renvoyer. */
    private function vendorShop(Request $request): Shop|JsonResponse
    {
        $user = $request->user();
        if (!$user->hasAnyRole(['vendeur', 'vendor'])) {
            return response()->json(['success' => false, 'message' => 'Vous n\'êtes pas vendeur'], 403);
        }

        $shop = $user->primaryShop;
        if (!$shop) {
            return response()->json(['success' => false, 'message' => 'Aucune boutique trouvée'], 404);
        }

        return $shop;
    }

    /**
     * Rapport CSV.
     *
     * Séparateur « ; » et BOM UTF-8 : sans eux, Excel en configuration
     * française ouvre le fichier sur une seule colonne et casse les accents.
     */
    private function exportCsv(Shop $shop, array $summary, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($shop, $summary) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");

            $line = fn (array $cells) => fputcsv($out, $cells, ';');

            $line(['Boutique', $shop->name]);
            $line(['Période', $summary['period']['label'] ?? '']);
            $line(['Du', $this->humanDate($summary['period']['from'] ?? null)]);
            $line(['Au', $this->humanDate($summary['period']['to'] ?? null)]);
            $line(['Édité le', now()->format('d/m/Y H:i')]);
            $line([]);

            $totals = $summary['totals'] ?? [];
            $line(['Indicateur', 'Valeur']);
            foreach ($this->totalLabels() as $key => $label) {
                $line([$label, $this->number($totals[$key] ?? 0)]);
            }
            $line([]);

            $line(['Détail par ' . (($summary['period']['granularity'] ?? 'day') === 'month' ? 'mois' : 'jour')]);
            $line(['Date', 'Visites', 'Produits consultés', 'Contacts', 'Commandes', 'Chiffre d\'affaires']);
            foreach ($summary['series'] ?? [] as $point) {
                $line([
                    $point['date'] ?? '',
                    $point['visits'] ?? 0,
                    $point['product_views'] ?? 0,
                    $point['contacts'] ?? 0,
                    $point['orders'] ?? 0,
                    $this->number($point['revenue'] ?? 0),
                ]);
            }

            if (!empty($summary['top_products'])) {
                $line([]);
                $line(['Produits les plus consultés']);
                $line(['Produit', 'Vues', 'Vendus', 'Chiffre d\'affaires', 'Taux de conversion (%)']);
                foreach ($summary['top_products'] as $product) {
                    $line([
                        $product['name'] ?? '',
                        $product['views'] ?? 0,
                        $product['items_sold'] ?? 0,
                        $this->number($product['revenue'] ?? 0),
                        $this->number($product['conversion_rate'] ?? 0),
                    ]);
                }
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /** Rapport PDF, rendu depuis une vue Blade dédiée. */
    private function exportPdf(Shop $shop, array $summary, string $filename): StreamedResponse
    {
        $pdf = Pdf::loadView('reports.shop-statistics', [
            'shop' => $shop,
            'summary' => $summary,
            'labels' => $this->totalLabels(),
            'generatedAt' => now(),
        ])->setPaper('a4', 'portrait');

        return response()->streamDownload(
            fn () => print($pdf->output()),
            $filename,
            ['Content-Type' => 'application/pdf'],
        );
    }

    /** Libellés des indicateurs, partagés par le CSV et le PDF. */
    private function totalLabels(): array
    {
        return [
            'visits' => 'Visites de la boutique',
            'unique_visitors' => 'Visiteurs uniques',
            'product_views' => 'Produits consultés',
            'contacts' => 'Prises de contact',
            'orders' => 'Commandes',
            'validated_orders' => 'Commandes validées',
            'pending_orders' => 'Commandes en attente',
            'cancelled_orders' => 'Commandes annulées',
            'items_sold' => 'Articles vendus',
            'revenue' => 'Chiffre d\'affaires (XAF)',
            'gross_sales' => 'Volume client (XAF)',
            'commission' => 'Commission ASSO (XAF)',
            'average_basket' => 'Panier moyen (XAF)',
            'conversion_rate' => 'Taux de conversion (%)',
        ];
    }

    private function number(mixed $value): string
    {
        return number_format((float) $value, 2, ',', ' ');
    }

    private function humanDate(?string $iso): string
    {
        return $iso ? \Illuminate\Support\Carbon::parse($iso)->format('d/m/Y') : '—';
    }
}
