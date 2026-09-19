<?php

namespace App\Support;

use App\Models\DelivererCompany;
use App\Models\Order;
use App\Services\OrderTrackingService;

/**
 * Bloc « livraison » d'une commande pour l'API : partenaire, catégorie, détail du
 * prix figé à la commande, numéro et lien de suivi transporteur, historique daté.
 */
class DeliveryPresenter
{
    public static function forOrder(Order $order): array
    {
        $snapshot = $order->delivery_breakdown ?? [];
        $company = $order->relationLoaded('deliveryCompany') ? $order->deliveryCompany : null;
        $isCarrier = $order->isCarrierDelivery();

        $trackingUrl = $company?->trackingUrl($order->carrier_tracking_number);
        if (!$trackingUrl && $order->is_wholesale && $order->carrier_tracking_number && $order->shipping_option_id) {
            $template = \App\Models\ImportShippingOption::whereKey($order->shipping_option_id)->value('tracking_url_template');
            $trackingUrl = $template ? str_replace('{number}', rawurlencode($order->carrier_tracking_number), $template) : null;
        }

        return [
            'mode' => $order->delivery_mode,
            'is_carrier' => $isCarrier,
            'company_name' => $snapshot['company_name'] ?? $company?->name,
            'service_type' => $snapshot['service_type'] ?? null,
            'service_type_label' => $snapshot['service_type_label'] ?? null,
            'service_mode' => $snapshot['service_mode'] ?? $company?->service_mode,
            'service_mode_label' => $snapshot['service_mode_label']
                ?? ($company ? (DelivererCompany::SERVICE_MODES[$company->service_mode] ?? null) : null),
            'route_label' => $snapshot['route_label'] ?? null,
            'delivery_option' => $snapshot['delivery_option'] ?? ($isCarrier && !$order->hasLastMileDelivery() ? 'agency_pickup' : 'home_delivery'),
            'delivery_option_label' => $snapshot['delivery_option_label'] ?? null,
            'last_mile' => $order->hasLastMileDelivery(),
            'lead_time' => $snapshot['lead_time'] ?? null,
            'conditions' => $snapshot['conditions'] ?? null,
            'price_grid' => $snapshot['price_grid'] ?? [],
            'weight_kg' => $order->shipping_weight_kg,
            'breakdown' => $snapshot !== [] ? array_intersect_key($snapshot, array_flip([
                'weight_kg', 'range_label', 'range_price', 'extra_kg', 'extra_per_kg', 'extra_price', 'legs',
                'carrier_price_ht', 'prices_exclude_vat', 'vat_rate', 'vat_amount', 'carrier_price',
                'asso_commission', 'total',
            ])) : null,
            'carrier_tracking_number' => $order->carrier_tracking_number,
            'carrier_tracking_url' => $trackingUrl,
            'tracking_status' => $order->tracking_status,
            'tracking_status_label' => $order->tracking_status ? (OrderTrackingService::STEPS[$order->tracking_status] ?? null) : null,
            // Acheteur : confirmer le retrait en agence. À domicile, le coursier clôture
            // avec le code à 6 chiffres.
            'can_confirm_reception' => $isCarrier && !$order->hasLastMileDelivery() && $order->status === 'shipped',
            'timeline' => $order->relationLoaded('trackingEvents') ? OrderTrackingService::timeline($order) : [],
        ];
    }
}
