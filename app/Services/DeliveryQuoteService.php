<?php

namespace App\Services;

use App\Models\DelivererCompany;
use App\Models\DeliveryPricelist;
use App\Models\DeliveryRoute;
use App\Models\DeliveryZone;
use App\Models\Product;
use App\Models\Setting;
use App\Support\CountryCode;
use App\Support\LocationFormatter;
use App\Support\WeightGrid;

/**
 * Chiffrage de la livraison d'un panier — source unique pour l'affichage avant
 * validation (GET /v1/delivery/partners) et pour la commande (OrderService::createOrder).
 *
 * - Le poids réel compte partout : poids total = Σ poids fiche × quantité.
 *   Un article sans poids bloque la livraison (décision propriétaire P4).
 * - Urbain : zones d'un partenaire (ville ou rayon réglable autour du centre), même
 *   ville que la boutique.
 * - Interurbain / international : trajets d'un partenaire (SOLEX Douala ↔ Yaoundé,
 *   DHL Chine → Cameroun) avec grille au poids. Deux options par trajet :
 *     « Retrait en agence »    = trajet d'agence à agence seul ;
 *     « Livraison à domicile » = trajet + zone urbaine du même partenaire dans la ville
 *                                d'arrivée (le coursier livre depuis l'agence, code à 6 chiffres).
 *   L'acheteur voit un prix unique ; les deux volets sont dans le détail.
 * - TVA ajoutée quand la grille du partenaire est hors taxe.
 * - Chaque offre porte le détail complet affiché à l'acheteur.
 */
class DeliveryQuoteService
{
    public const DEFAULT_RADIUS_KM = 10;
    public const DEFAULT_VAT_RATE = 19.25;

    public static function radiusKm(): float
    {
        return (float) Setting::get('delivery_zone_radius_km', self::DEFAULT_RADIUS_KM);
    }

    public static function vatRate(): float
    {
        return (float) Setting::get('delivery_vat_rate', self::DEFAULT_VAT_RATE);
    }

    /**
     * Toutes les offres de livraison pour un panier.
     *
     * @param array $items [['product_id' => int, 'quantity' => int], …]
     */
    public function quotes(array $items, ?float $lat, ?float $lng, ?string $city, ?string $country = null): array
    {
        $cart = $this->cart($items);
        [$destCity, $destCountry] = $this->destination($city, $country);
        [$originCity, $originCountry] = $cart['origin'];

        $result = [
            'available' => false,
            'reason' => null,
            'message' => null,
            'weight_kg' => $cart['weight_kg'],
            'missing_weight_products' => $cart['missing_weight'],
            'origin' => ['city' => $originCity, 'country' => $originCountry, 'country_name' => CountryCode::name($originCountry)],
            'destination' => ['city' => $destCity, 'country' => $destCountry, 'country_name' => CountryCode::name($destCountry)],
            'vat_rate' => self::vatRate(),
            'partners' => [],
        ];

        if ($cart['missing_weight'] !== []) {
            $result['reason'] = 'missing_weight';
            $result['message'] = 'Livraison impossible à chiffrer : le vendeur doit renseigner le poids de '
                . implode(', ', $cart['missing_weight']) . '.';
            return $result;
        }

        $partners = array_merge(
            $this->localQuotes($cart, $lat, $lng, $destCity, $destCountry),
            $this->carrierQuotes($cart, $destCity, $destCountry, $lat, $lng),
        );

        usort($partners, fn ($a, $b) => [$a['sort_group'], $a['delivery_price']] <=> [$b['sort_group'], $b['delivery_price']]);
        $result['partners'] = array_map(fn ($p) => array_diff_key($p, ['sort_group' => 1]), $partners);
        $result['available'] = $result['partners'] !== [];

        if (!$result['available']) {
            $result['reason'] = 'no_partner';
            $result['message'] = 'Aucun partenaire de livraison ne dessert ' . ($destCity ?: 'cette adresse')
                . ($originCity ? " depuis {$originCity}" : '') . ' pour ' . $this->formatKg($cart['weight_kg']) . '.';
        }

        return $result;
    }

    /**
     * L'offre choisie par l'acheteur, recalculée côté serveur au moment de la commande.
     *
     * @throws \Exception si l'offre n'est plus disponible
     */
    public function quoteFor(array $items, int $companyId, ?int $zoneId, ?int $routeId, ?float $lat, ?float $lng, ?string $city, ?string $country = null): array
    {
        $result = $this->quotes($items, $lat, $lng, $city, $country);

        if ($result['reason'] === 'missing_weight') {
            throw new \Exception($result['message']);
        }

        foreach ($result['partners'] as $partner) {
            if ($partner['company_id'] !== $companyId) {
                continue;
            }
            // Trajet : « retrait en agence » (sans zone) ou « à domicile » (zone d'arrivée).
            if ($routeId && $partner['route_id'] === $routeId && $partner['zone_id'] === $zoneId) {
                return $partner + ['weight_kg' => $result['weight_kg']];
            }
            if (!$routeId && $zoneId && $partner['zone_id'] === $zoneId) {
                return $partner + ['weight_kg' => $result['weight_kg']];
            }
        }

        // Ancienne app : seule la zone est envoyée, sans ville → on accepte la zone
        // choisie telle quelle si elle appartient bien au livreur.
        if (!$routeId && $zoneId) {
            $cart = $this->cart($items);
            $zone = DeliveryZone::with(['activePricelist', 'delivererCompany'])
                ->where('id', $zoneId)->where('deliverer_company_id', $companyId)->where('is_active', true)->first();
            if ($zone && $zone->activePricelist && $zone->delivererCompany) {
                $quote = $this->localQuote($zone->delivererCompany, $zone, $cart, null);
                if ($quote) {
                    return $quote + ['weight_kg' => $cart['weight_kg']];
                }
            }
        }

        throw new \Exception("Ce mode de livraison n'est plus disponible pour cette adresse. Veuillez en choisir un autre.");
    }

    /** Panier : poids total, articles sans poids, origine (ville/pays de la boutique). */
    public function cart(array $items): array
    {
        $weight = 0.0;
        $missing = [];
        $origin = [null, null];
        $category = null;
        $products = Product::with('shop')->whereIn('id', collect($items)->pluck('product_id'))->get()->keyBy('id');

        foreach ($items as $item) {
            $product = $products->get((int) $item['product_id']);
            if (!$product) {
                continue;
            }
            $quantity = max(1, (int) ($item['quantity'] ?? 1));

            if ($product->type === 'service') {
                // Une prestation n'a pas de poids ni de colis.
            } elseif (($unit = $product->weightKg()) !== null) {
                $weight += $unit * $quantity;
            } else {
                $missing[] = "« {$product->name} »";
            }

            if ($origin === [null, null]) {
                $origin = $this->productOrigin($product);
            }
            $category ??= $product->weight_category;
        }

        return [
            'weight_kg' => round($weight, 3),
            'missing_weight' => array_values(array_unique($missing)),
            'origin' => $origin,
            'weight_category' => $category ?? 'X-small',
        ];
    }

    /** @return array{0: ?string, 1: ?string} [ville, code pays] de la boutique qui expédie */
    private function productOrigin(Product $product): array
    {
        $shop = $product->shop;
        $city = $shop?->city;
        $country = CountryCode::normalize($shop?->country);

        if ((!$city || !$country) && $shop?->address) {
            [$parsedCity, $parsedCountry] = LocationFormatter::parse($shop->address);
            $city = $city ?: $parsedCity;
            $country = $country ?: CountryCode::normalize($parsedCountry);
        }

        // Produit importé sans boutique localisée : pays d'origine de la fiche.
        $country = $country ?: CountryCode::normalize($product->origin_country);

        return [$city, $country ?: ($city ? 'CM' : null)];
    }

    /** @return array{0: ?string, 1: ?string} [ville, code pays] de l'acheteur */
    private function destination(?string $city, ?string $country): array
    {
        $code = CountryCode::normalize($country);
        $destCity = null;

        if ($city && preg_match('/[a-zA-Z]/', $city)) {
            [$parsedCity, $parsedCountry] = LocationFormatter::parse($city);
            $destCity = $parsedCity ?: trim(explode(',', $city)[0]);
            $code = $code ?: CountryCode::normalize($parsedCountry);
        }

        // Vente de produits : un seul sens, vers le Cameroun.
        return [$destCity, $code ?: 'CM'];
    }

    private function localQuotes(array $cart, ?float $lat, ?float $lng, ?string $destCity, ?string $destCountry): array
    {
        [$originCity, $originCountry] = $cart['origin'];

        // Livreur local = même ville : un colis Yaoundé → Garoua passe par un transporteur.
        if ($originCountry && $destCountry && $originCountry !== $destCountry) {
            return [];
        }
        if ($originCity && $destCity && !CountryCode::sameCity($originCity, $destCity)) {
            return [];
        }

        $radius = self::radiusKm();
        $quotes = [];

        $companies = DelivererCompany::where('is_active', true)
            ->with(['deliveryZones' => fn ($q) => $q->where('is_active', true)->with('activePricelist'), 'user'])
            ->get();

        foreach ($companies as $company) {
            foreach ($company->deliveryZones as $zone) {
                $distance = ($lat && $lng && $zone->center_latitude && $zone->center_longitude)
                    ? $this->distanceKm($lat, $lng, (float) $zone->center_latitude, (float) $zone->center_longitude)
                    : null;

                // Couverture : la ville de la zone, sinon le rayon réglé dans l'admin.
                $cityMatches = $destCity && $zone->city && CountryCode::sameCity($destCity, $zone->city);
                $inRadius = $distance !== null && $distance <= $radius;
                if (!$cityMatches && !$inRadius) {
                    continue;
                }

                $quote = $this->localQuote($company, $zone, $cart, $distance);
                if ($quote) {
                    $quotes[] = $quote;
                }
            }
        }

        return $quotes;
    }

    private function localQuote(DelivererCompany $company, DeliveryZone $zone, array $cart, ?float $distance): ?array
    {
        $pricelist = $zone->activePricelist;
        if (!$pricelist) {
            return null;
        }
        if ($company->max_weight_kg && $cart['weight_kg'] > $company->max_weight_kg) {
            return null;
        }

        $zonePrice = $this->zonePrice($pricelist, $cart);
        if (!$zonePrice) {
            return null;
        }

        return $this->offer(
            company: $company,
            carrierPrice: $zonePrice['price'],
            assoCommission: (float) $pricelist->asso_commission,
            grid: $zonePrice['grid'],
            rangeLabel: $zonePrice['range_label'],
            weightKg: $cart['weight_kg'],
            extra: [
                'delivery_mode' => 'local',
                'service_type' => DelivererCompany::SERVICE_LOCAL,
                'zone_id' => $zone->id,
                'zone_name' => $zone->name,
                'route_id' => null,
                'route_label' => $zone->city ? "Livraison dans {$zone->city}" : $zone->name,
                'city' => $zone->city,
                'zone_latitude' => (float) $zone->center_latitude,
                'zone_longitude' => (float) $zone->center_longitude,
                'distance_km' => $distance !== null ? round($distance, 2) : null,
                'lead_time' => $pricelist->lead_time,
                // En zone urbaine, le coursier (ex. SOLEX) livre à l'adresse de l'acheteur,
                // même si le partenaire travaille d'agence en agence en interurbain.
                'service_mode' => DelivererCompany::MODE_DOOR,
                'pricing_type' => $pricelist->pricing_type,
                'price_grid' => $zonePrice['describe'],
                'delivery_option' => 'home_delivery',
                'sort_group' => 0,
                'deliverer' => $company->user ? [
                    'id' => $company->user->id,
                    'name' => trim($company->user->first_name . ' ' . $company->user->last_name),
                    'phone' => $company->user->phone,
                ] : null,
            ],
        );
    }

    /**
     * Prix d'une zone urbaine pour le poids du panier (forfait, tranches de poids, ancien
     * type par catégorie). Null si le colis dépasse la grille.
     *
     * @return array{price: float, range_label: string, grid: ?array, describe: array}|null
     */
    private function zonePrice(DeliveryPricelist $pricelist, array $cart): ?array
    {
        switch ($pricelist->pricing_type) {
            case DeliveryPricelist::PRICING_TYPE_FIXED:
                return ['price' => (float) ($pricelist->pricing_data['price'] ?? 0), 'range_label' => 'Forfait', 'grid' => null, 'describe' => []];
            case DeliveryPricelist::PRICING_TYPE_VOLUMETRIC_WEIGHT:
                // Tranches de poids réel (kg) — nom historique du type conservé.
                $grid = WeightGrid::price($pricelist->pricing_data ?? [], $cart['weight_kg']);

                return $grid ? [
                    'price' => $grid['price'],
                    'range_label' => $grid['range_label'],
                    'grid' => $grid,
                    'describe' => WeightGrid::describe($pricelist->pricing_data ?? []),
                ] : null;
            default:
                // Ancien type par catégorie de colis : conservé pour les grilles existantes.
                return [
                    'price' => $pricelist->calculatePrice(['category' => $cart['weight_category']]),
                    'range_label' => 'Catégorie ' . $cart['weight_category'],
                    'grid' => null,
                    'describe' => [],
                ];
        }
    }

    /**
     * Zone urbaine du partenaire qui couvre l'acheteur dans la ville d'arrivée
     * (dernier kilomètre depuis l'agence). La moins chère si plusieurs.
     *
     * @return array{zone: DeliveryZone, pricelist: DeliveryPricelist, price: array}|null
     */
    private function lastMileZone(DelivererCompany $company, array $cart, ?string $destCity, ?float $lat, ?float $lng): ?array
    {
        $best = null;
        foreach ($company->deliveryZones as $zone) {
            $pricelist = $zone->activePricelist;
            if (!$zone->is_active || !$pricelist) {
                continue;
            }
            $cityMatches = $destCity && $zone->city && CountryCode::sameCity($destCity, $zone->city);
            $inRadius = $lat && $lng && $zone->center_latitude && $zone->center_longitude
                && $this->distanceKm($lat, $lng, (float) $zone->center_latitude, (float) $zone->center_longitude) <= self::radiusKm();
            if (!$cityMatches && !$inRadius) {
                continue;
            }
            $price = $this->zonePrice($pricelist, $cart);
            if ($price && (!$best || $price['price'] < $best['price']['price'])) {
                $best = ['zone' => $zone, 'pricelist' => $pricelist, 'price' => $price];
            }
        }

        return $best;
    }

    private function carrierQuotes(array $cart, ?string $destCity, ?string $destCountry, ?float $lat = null, ?float $lng = null): array
    {
        [$originCity, $originCountry] = $cart['origin'];
        if (!$originCountry || !$destCountry) {
            return [];
        }
        // Même ville : pas de transport interurbain.
        if ($originCountry === $destCountry && $originCity && CountryCode::sameCity($originCity, $destCity)) {
            return [];
        }

        $routes = DeliveryRoute::where('is_active', true)
            ->whereHas('company', fn ($q) => $q->where('is_active', true))
            ->with(['company.deliveryZones.activePricelist'])
            ->get()
            ->filter(fn (DeliveryRoute $r) => $r->serves($originCountry, $originCity, $destCountry, $destCity));

        $quotes = [];
        foreach ($routes as $route) {
            $company = $route->company;
            if ($company->max_weight_kg && $cart['weight_kg'] > $company->max_weight_kg) {
                continue;
            }
            $grid = WeightGrid::price($route->pricing_data ?? [], $cart['weight_kg']);
            if (!$grid) {
                continue;
            }

            $base = [
                'delivery_mode' => 'carrier',
                'service_type' => $route->origin_country === $route->destination_country
                    ? DelivererCompany::SERVICE_INTERCITY
                    : DelivererCompany::SERVICE_INTERNATIONAL,
                'zone_id' => null,
                'zone_name' => null,
                'route_id' => $route->id,
                'route_label' => $route->label(),
                'city' => $destCity,
                'distance_km' => null,
                'lead_time' => $route->lead_time,
                'service_mode' => $company->service_mode,
                'delivery_option' => $company->service_mode === DelivererCompany::MODE_AGENCY ? 'agency_pickup' : 'home_delivery',
                'pricing_type' => 'weight_grid',
                'price_grid' => WeightGrid::describe($route->pricing_data ?? []),
                'sort_group' => 1,
                'deliverer' => null,
            ];
            $routeLeg = [
                'label' => $company->service_mode === DelivererCompany::MODE_AGENCY
                    ? 'Transport ' . $route->label() . " (d'agence à agence)"
                    : 'Transport ' . $route->label(),
                'price' => round($grid['price']),
            ];

            // Option 1 : trajet seul (retrait en agence pour un partenaire « agence à agence »).
            $quotes[] = $this->offer(
                company: $company,
                carrierPrice: $grid['price'],
                assoCommission: (float) $route->asso_commission,
                grid: $grid,
                rangeLabel: $grid['range_label'],
                weightKg: $cart['weight_kg'],
                extra: $base + ['legs' => [$routeLeg]],
            );

            // Option 2 : agence → domicile par le coursier du partenaire dans la ville d'arrivée.
            if ($company->service_mode !== DelivererCompany::MODE_AGENCY) {
                continue;
            }
            $lastMile = $this->lastMileZone($company, $cart, $destCity, $lat, $lng);
            if (!$lastMile) {
                continue;
            }
            $zone = $lastMile['zone'];
            $zoneCity = $zone->city ?: $destCity;
            $quotes[] = $this->offer(
                company: $company,
                carrierPrice: $grid['price'] + $lastMile['price']['price'],
                assoCommission: (float) $route->asso_commission + (float) $lastMile['pricelist']->asso_commission,
                grid: $grid,
                rangeLabel: $grid['range_label'],
                weightKg: $cart['weight_kg'],
                extra: [
                    'zone_id' => $zone->id,
                    'zone_name' => $zone->name,
                    'service_mode' => DelivererCompany::MODE_DOOR,
                    'delivery_option' => 'home_delivery',
                    'lead_time' => collect([$route->lead_time, $lastMile['pricelist']->lead_time])->filter()->implode(' + ') ?: null,
                    'legs' => [
                        $routeLeg,
                        [
                            'label' => 'Livraison à domicile' . ($zoneCity ? " à {$zoneCity}" : '') . " depuis l'agence",
                            'price' => round($lastMile['price']['price']),
                        ],
                    ],
                ] + $base,
            );
        }

        return $quotes;
    }

    /** Offre complète : tout ce que l'acheteur doit voir avant de valider. */
    private function offer(DelivererCompany $company, float $carrierPrice, float $assoCommission, ?array $grid, string $rangeLabel, float $weightKg, array $extra): array
    {
        $carrierPrice = round($carrierPrice);
        $vatRate = $company->prices_exclude_vat ? self::vatRate() : 0.0;
        $vatAmount = round($carrierPrice * $vatRate / 100);
        $carrierTotal = $carrierPrice + $vatAmount;
        $total = $carrierTotal + round($assoCommission);

        return $extra + [
            'company_id' => $company->id,
            'company_name' => $company->name,
            'company_phone' => $company->phone,
            'company_email' => $company->email,
            'company_description' => $company->description,
            'company_logo' => $company->logo ? asset('storage/' . $company->logo) : null,
            'service_type' => $extra['service_type'],
            'service_type_label' => DelivererCompany::SERVICE_TYPES[$extra['service_type']],
            'service_mode' => $extra['service_mode'],
            'service_mode_label' => DelivererCompany::SERVICE_MODES[$extra['service_mode']] ?? $extra['service_mode'],
            'delivery_option' => $extra['delivery_option'] ?? 'home_delivery',
            'delivery_option_label' => ($extra['delivery_option'] ?? 'home_delivery') === 'agency_pickup'
                ? 'Retrait en agence'
                : 'Livraison à domicile',
            'conditions' => $company->conditions,
            'max_weight_kg' => $company->max_weight_kg,
            'has_tracking_link' => (bool) $company->tracking_url_template,
            'delivery_price' => $total,
            'formatted_delivery_price' => number_format($total, 0, ',', ' ') . ' FCFA',
            // Compatibilité : prix du livreur (TTC) et commission séparés.
            'base_price' => $carrierTotal,
            'asso_commission' => round($assoCommission),
            'breakdown' => [
                'weight_kg' => $weightKg,
                'range_label' => $rangeLabel,
                'range_price' => $grid['range_price'] ?? $carrierPrice,
                'extra_kg' => $grid['extra_kg'] ?? 0,
                'extra_per_kg' => $grid['extra_per_kg'] ?? 0,
                'extra_price' => $grid['extra_price'] ?? 0,
                // Volets du prix (trajet d'agence à agence + livraison à domicile), HT.
                'legs' => $extra['legs'] ?? [],
                'carrier_price_ht' => $carrierPrice,
                'prices_exclude_vat' => (bool) $company->prices_exclude_vat,
                'vat_rate' => $vatRate,
                'vat_amount' => $vatAmount,
                'carrier_price' => $carrierTotal,
                'asso_commission' => round($assoCommission),
                'total' => $total,
            ],
        ];
    }

    private function distanceKm(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        return 6371 * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    private function formatKg(float $kg): string
    {
        return rtrim(rtrim(number_format($kg, 3, ',', ' '), '0'), ',') . ' kg';
    }
}
