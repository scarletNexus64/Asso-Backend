<?php

namespace App\Support;

use App\Models\DeliveryAgency;
use App\Models\Order;
use Illuminate\Support\Str;

/**
 * Remise d'une commande nationale au transporteur (SOLEX…) : l'agence de départ et
 * le numéro de suivi sont déterminés par ASSO, le vendeur ne fait que confirmer.
 */
class CarrierHandover
{
    /** Le vendeur peut déclarer la remise de cette commande. */
    public static function available(Order $order): bool
    {
        return $order->isCarrierDelivery()
            && ! $order->isInternationalExport()
            && in_array($order->status, ['confirmed', 'preparing'], true);
    }

    /**
     * Transporteur avec suivi en ligne (DHL, FedEx) : seul son bordereau donne un
     * numéro valable, le vendeur le saisit encore.
     */
    public static function needsCarrierNumber(Order $order): bool
    {
        return filled($order->deliveryCompany?->tracking_url_template);
    }

    /** Le transporteur vient chercher le colis à la boutique (pas de dépôt en agence). */
    public static function collectedAtShop(Order $order): bool
    {
        $legs = $order->delivery_breakdown['legs'] ?? [];

        return collect(is_array($legs) ? $legs : [])->contains(fn ($leg) => ($leg['kind'] ?? null) === 'collection');
    }

    /** Agence du transporteur dans la zone de départ de la commande. */
    public static function originAgency(Order $order): ?DeliveryAgency
    {
        if (! $order->delivery_company_id) {
            return null;
        }
        $agencies = DeliveryAgency::where('deliverer_company_id', $order->delivery_company_id)
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('is_international_reference')->orWhere('is_international_reference', false))
            ->get();
        if ($agencies->isEmpty()) {
            return null;
        }

        // Réseau territorial : le code agence finit par le code département (SOLEX-CM005004).
        $breakdown = $order->delivery_breakdown ?? [];
        $department = $breakdown['origin_coverage']['department_code']
            ?? ($breakdown['legs'][0]['from']['department_code'] ?? null);
        if ($department) {
            $match = $agencies->first(fn ($a) => $a->code && Str::endsWith(strtoupper($a->code), '-'.strtoupper($department)));
            if ($match) {
                return $match;
            }
        }

        // Sinon : ville de la boutique, puis ville de départ du trajet.
        $order->loadMissing('items.product.shop');
        $cities = array_filter([
            $order->items->first()?->product?->shop?->city,
            $order->delivery_route_id ? \App\Models\DeliveryRoute::whereKey($order->delivery_route_id)->value('origin_city') : null,
        ]);
        foreach ($cities as $city) {
            $match = $agencies->first(fn ($a) => CountryCode::sameCity($a->city, $city));
            if ($match) {
                return $match;
            }
        }

        return null;
    }

    /** Numéro de suivi ASSO unique, ex. SOL-50-7K3QD. */
    public static function generateTrackingNumber(Order $order): string
    {
        $prefix = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', (string) ($order->delivery_breakdown['company_name'] ?? $order->deliveryCompany?->name ?? 'ASO')), 0, 3)) ?: 'ASO';
        do {
            $number = $prefix.'-'.$order->id.'-'.strtoupper(Str::random(5));
        } while (Order::where('carrier_tracking_number', $number)->exists());

        return $number;
    }

    public static function locationLabel(?DeliveryAgency $agency): ?string
    {
        if (! $agency) {
            return null;
        }

        return Str::limit(trim($agency->name.($agency->address ? ' — '.$agency->address : '')), 150, '');
    }

    /** Informations de remise pour l'app vendeur (null si la remise n'est pas possible). */
    public static function present(Order $order): ?array
    {
        if (! self::available($order)) {
            return null;
        }
        $agency = self::originAgency($order);

        return [
            'agency' => $agency?->only(['id', 'code', 'name', 'city', 'address', 'phone']),
            'collected_at_shop' => self::collectedAtShop($order),
            'requires_tracking_number' => self::needsCarrierNumber($order),
        ];
    }
}
