<?php

namespace Database\Seeders;

use App\Models\DelivererCompany;
use App\Models\DeliveryRoute;
use App\Models\Setting;
use App\Services\DeliveryQuoteService;
use Illuminate\Database\Seeder;

/**
 * P4 — partenaires logistiques réels. Idempotent (relançable sans doublon).
 *
 * - SOLEX : transport interurbain des plis et colis, départ Douala et vice versa,
 *   d'agence en agence, prix en FCFA hors taxe (grille fournie par le propriétaire).
 *   Les zones urbaines SOLEX (Livreurs → SOLEX) servent aussi au dernier kilomètre :
 *   agence d'arrivée → domicile, prix ajouté au trajet.
 * - DHL / FedEx : créés inactifs, en attente des grilles réelles (Étranger → Cameroun).
 */
class DeliveryPartnersSeeder extends Seeder
{
    /** Ville => [plis 0–2 kg, colis ≤ 10 kg, kg supplémentaire, délai de route] */
    private const SOLEX_FROM_DOUALA = [
        'Yaoundé' => [2500, 4000, 150, '24 h'],
        'Bertoua' => [3500, 5000, 150, '48 h'],
        'Bafoussam' => [2500, 4000, 150, '24 h'],
        'Ngaoundéré' => [3500, 11000, 400, '48 h'],
        'Garoua' => [4500, 11000, 500, '72 h'],
        'Maroua' => [5000, 12000, 500, '72 h'],
        'Nkongsamba' => [2500, 3500, 100, '24 h'],
        'Limbé' => [2500, 3500, 100, '24 h'],
    ];

    public function run(): void
    {
        if (Setting::get('delivery_zone_radius_km') === null) {
            Setting::set('delivery_zone_radius_km', DeliveryQuoteService::DEFAULT_RADIUS_KM, 'string', 'delivery', 'Rayon de couverture autour du centre d\'une zone de livraison (km)');
        }
        if (Setting::get('delivery_vat_rate') === null) {
            Setting::set('delivery_vat_rate', DeliveryQuoteService::DEFAULT_VAT_RATE, 'string', 'delivery', 'TVA ajoutée aux grilles de livraison hors taxe (%)');
        }

        $solex = DelivererCompany::updateOrCreate(['name' => 'SOLEX'], [
            'description' => 'Transport interurbain des plis et colis au Cameroun, départ Douala et vice versa.',
            'service_type' => DelivererCompany::SERVICE_INTERCITY,
            'service_mode' => DelivererCompany::MODE_AGENCY,
            'prices_exclude_vat' => true,
            'conditions' => "Interurbain : transport d'agence SOLEX à agence SOLEX, puis au choix retrait en agence ou livraison à domicile par un coursier SOLEX (prix de la zone d'arrivée ajouté).\n"
                . "Plis & paquets : 0 à 2 kg. Colis : jusqu'à 10 kg, puis chaque kg supplémentaire est facturé.\n"
                . "Délai de route indicatif, hors week-ends et jours fériés.",
            'is_active' => true,
        ]);

        foreach (self::SOLEX_FROM_DOUALA as $city => [$plis, $colis, $extraKg, $leadTime]) {
            DeliveryRoute::updateOrCreate(
                [
                    'deliverer_company_id' => $solex->id,
                    'origin_country' => 'CM',
                    'origin_city' => 'Douala',
                    'destination_country' => 'CM',
                    'destination_city' => $city,
                ],
                [
                    'bidirectional' => true,
                    'pricing_data' => [
                        'ranges' => [
                            ['min' => 0, 'max' => 2, 'price' => $plis, 'label' => 'Plis & paquets'],
                            ['min' => 2, 'max' => 10, 'price' => $colis, 'label' => 'Colis'],
                        ],
                        'extra_per_kg' => $extraKg,
                    ],
                    'asso_commission' => 0,
                    'lead_time' => $leadTime,
                    'is_active' => true,
                ]
            );
        }

        foreach ([
            'DHL Express' => 'https://www.dhl.com/cm-fr/home/tracking.html?tracking-id={number}',
            'FedEx' => 'https://www.fedex.com/fedextrack/?trknbr={number}',
        ] as $name => $trackingUrl) {
            DelivererCompany::firstOrCreate(['name' => $name], [
                'description' => 'Livraison internationale vers le Cameroun.',
                'service_type' => DelivererCompany::SERVICE_INTERNATIONAL,
                'service_mode' => DelivererCompany::MODE_DOOR,
                'prices_exclude_vat' => true,
                'tracking_url_template' => $trackingUrl,
                // Inactif tant que la grille réelle n'est pas saisie dans l'admin.
                'is_active' => false,
            ]);
        }
    }
}
