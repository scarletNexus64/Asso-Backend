<?php

namespace App\Services\RealEstate;

use App\Models\RealEstate\Cabinet;
use App\Models\RealEstate\Listing;
use App\Models\RealEstate\Unit;
use App\Models\Setting;

/**
 * Seul endroit où se calculent les prix de la rubrique Immobilier.
 *
 * - Le prix affiché est le prix de référence, sans majoration.
 * - Le bailleur reçoit toute l'avance calculée sur le prix affiché.
 * - Les frais ASSO et partenaire s'ajoutent à cette avance, sans retenue.
 * - Les taux restent calculés sur le prix de référence (net_price).
 */
class RealEstatePricingService
{
    public const DEFAULTS = [
        'immo_asso_markup_rate' => 10,
        'immo_partner_commission_rate' => 5,
        'immo_default_cabinet_id' => 0,
        'immo_land_default_deposit_percent' => 10,
        'immo_visit_window_days' => 7,
        'immo_land_release_days' => 7,
        'immo_low_stock_threshold' => 4,
        'immo_popular_views' => 100,
    ];

    public static function setting(string $key): float
    {
        return (float) Setting::get($key, self::DEFAULTS[$key] ?? 0);
    }

    public function markupRate(string $type): float
    {
        return self::setting('immo_asso_markup_rate');
    }

    public function partnerCommissionRate(): float
    {
        return self::setting('immo_partner_commission_rate');
    }

    /** Commission partner for this listing, or the configured default partner. */
    public function commissionCabinet(Listing $listing): ?Cabinet
    {
        if ($listing->cabinet_id) {
            $cabinet = $listing->relationLoaded('cabinet') ? $listing->cabinet : $listing->cabinet()->first();

            return $cabinet?->is_active ? $cabinet : null;
        }

        return Cabinet::defaultPartner();
    }

    /** Prix public servant au calcul de l’avance intégralement versée, arrondi au franc. */
    public function displayed(int $net, string $type): int
    {
        return max(0, $net);
    }

    /**
     * Morceaux achetés sur un terrain : un lot × [quantity], ou tout ce qui
     * reste en vente ([whole]). Une maison réserve toujours une seule place.
     *
     * @return list<array{unit: Unit, quantity: int}>
     */
    public function selection(Listing $listing, ?Unit $unit, int $quantity = 1, bool $whole = false): array
    {
        if (! $listing->isLand()) {
            return $unit ? [['unit' => $unit, 'quantity' => 1]] : [];
        }
        if ($whole) {
            $units = $listing->relationLoaded('units') ? $listing->units : $listing->units()->get();

            return $units->where('units_available', '>', 0)->sortBy('id')
                ->map(fn (Unit $u) => ['unit' => $u, 'quantity' => (int) $u->units_available])
                ->values()->all();
        }

        return $unit ? [['unit' => $unit, 'quantity' => max(1, $quantity)]] : [];
    }

    /**
     * Décomposition d'un paiement. Tous les montants sont en FCFA.
     * [$lots] (voir [selection]) : prix des morceaux additionnés pour un terrain.
     *
     * @param  list<array{unit: Unit, quantity: int}>|null  $lots
     * @return array{unit_price:int, advance_months:?int, base_amount:int, commission:int,
     *               total:int, owner_amount:int, platform_amount:int, deposit_percent:?float,
     *               quantity:int, lots:list<array{unit_id:int, label:string, quantity:int, unit_price:int}>}
     */
    public function quote(Listing $listing, ?Unit $unit = null, ?int $advanceMonths = null, ?array $lots = null): array
    {
        if ($lots === null || ! $listing->isLand()) {
            $lots = $unit ? [['unit' => $unit, 'quantity' => 1]] : [];
        }
        $multi = $listing->isLand() && $lots !== [];
        $net = $multi
            ? (int) array_sum(array_map(fn ($l) => (int) $l['unit']->net_price * $l['quantity'], $lots))
            : (int) ($unit?->net_price ?? $listing->net_price);
        $unitPrice = $this->displayed($net, $listing->type);

        if ($listing->type === Listing::TYPE_LAND) {
            $percent = (float) ($listing->deposit_percent ?: self::setting('immo_land_default_deposit_percent'));
            $reference = (int) round($net * $percent / 100);
            $owner = (int) round($unitPrice * $percent / 100);
            $months = null;
        } else {
            $months = max(1, (int) ($advanceMonths ?: $listing->advance_months ?: 1));
            $percent = null;
            $reference = $net * $months;
            $owner = $unitPrice * $months;
        }

        $platform = (int) round($reference * $this->markupRate($listing->type) / 100);
        $commission = (int) round($reference * $this->partnerCommissionRate() / 100);
        $total = $owner + $platform + $commission;

        return [
            'unit_price' => $unitPrice,
            'advance_months' => $months,
            'deposit_percent' => $percent,
            'base_amount' => $owner,
            'commission' => $commission,
            'total' => $total,
            'owner_amount' => $owner,
            'platform_amount' => $platform,
            'quantity' => $lots === [] ? 1 : (int) array_sum(array_column($lots, 'quantity')),
            'lots' => array_map(fn ($l) => [
                'unit_id' => $l['unit']->id,
                'label' => $l['unit']->label,
                'quantity' => $l['quantity'],
                'unit_price' => $this->displayed((int) $l['unit']->net_price, $listing->type),
            ], $lots),
        ];
    }
}
