<?php

namespace Database\Seeders;

use App\Models\DelivererCompany;
use App\Models\DeliveryCityGrid;
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

    /** SOLEX Douala — zones de couverture urbaine (légende de la grille). */
    private const SOLEX_DOUALA_ZONES = [
        1 => ['Bali', 'Bonapriso', 'Bonanjo', 'Aéroport', 'Deido', 'New-Bell'],
        2 => ['Akwa-Nord', 'Mboppi'],
        3 => ['Bonamoussadi', 'Kotto', 'Makèpè', 'Bonabéri', 'Ndobo'],
        4 => ['Zone Portuaire', 'Essengué', 'Bois des Singes'],
        5 => ['Elf axe lourd', 'Borne 10', 'Nyassa', 'Nyalla', 'Logbaba', 'PK8', 'Cité des Palmiers', 'Ndogbong', 'Béedi'],
        6 => ['Dakar', 'Ndokoti', 'BP Cité', 'Ange Raphaël'],
        7 => ['Logpom', 'Lendi', 'Logbessou', 'PK14', 'Mbanguè'],
    ];

    /**
     * SOLEX Douala — prix HT par véhicule, zone de départ → zone d'arrivée (grille
     * symétrique, triangle supérieur de la feuille : ligne = zone de départ).
     * Poids max. et délais : estimations à confirmer avec SOLEX (modifiables dans l'admin).
     */
    private const SOLEX_DOUALA_VEHICLES = [
        ['code' => 'moto', 'label' => 'Moto', 'max_weight_kg' => 30, 'lead_time' => '1 h à 3 h', 'rows' => [
            1 => [1000, 1000, 1500, 1500, 2000, 1500, 2500],
            2 => [1000, 1500, 1500, 2000, 2000, 2500],
            3 => [1000, 2500, 2500, 1500, 1500],
            4 => [1000, 2500, 2500, 2500],
            5 => [1000, 2000, 2500],
            6 => [1000, 2000],
            7 => [1000],
        ]],
        ['code' => 'tricycle', 'label' => 'Tricycle', 'max_weight_kg' => 300, 'lead_time' => '2 h à 4 h', 'rows' => [
            1 => [2000, 2000, 3000, 3000, 4000, 3000, 5000],
            2 => [2000, 3000, 3000, 4000, 4000, 5000],
            3 => [2000, 5000, 5000, 3000, 3000],
            4 => [2000, 5000, 5000, 5000],
            5 => [2000, 4000, 5000],
            6 => [2000, 4000],
            7 => [2000],
        ]],
        ['code' => '600kg', 'label' => 'Camionnette 600 kg', 'max_weight_kg' => 600, 'lead_time' => '3 h à 6 h', 'rows' => [
            1 => [3000, 3000, 4500, 4500, 6000, 4500, 7500],
            2 => [3000, 4500, 4500, 6000, 6000, 7500],
            3 => [3000, 7500, 7500, 4500, 4500],
            4 => [3000, 7500, 7500, 7500],
            5 => [3000, 6000, 7500],
            6 => [3000, 6000],
            7 => [3000],
        ]],
        ['code' => '1t', 'label' => 'Camion 1 tonne', 'max_weight_kg' => 1000, 'lead_time' => '3 h à 6 h', 'rows' => [
            1 => [4000, 4000, 6000, 6000, 8000, 6000, 10000],
            2 => [4000, 6000, 6000, 8000, 8000, 10000],
            3 => [4000, 10000, 10000, 6000, 6000],
            4 => [4000, 10000, 10000, 10000],
            5 => [4000, 8000, 10000],
            6 => [4000, 8000],
            7 => [4000],
        ]],
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
            'description' => 'Livraison urbaine à Douala (moto, tricycle, camionnette, camion) et transport interurbain des plis et colis, départ Douala et vice versa.',
            'service_type' => DelivererCompany::SERVICE_INTERCITY,
            'service_mode' => DelivererCompany::MODE_AGENCY,
            'prices_exclude_vat' => true,
            'conditions' => "Urbain (Douala) : livraison à domicile par un coursier SOLEX, prix selon la zone de départ, la zone d'arrivée et le véhicule (moto, tricycle, camionnette, camion).\n"
                . "Interurbain : transport d'agence SOLEX à agence SOLEX, puis au choix retrait en agence ou livraison à domicile par un coursier SOLEX (prix de la zone d'arrivée ajouté).\n"
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

        DeliveryCityGrid::updateOrCreate(
            ['deliverer_company_id' => $solex->id, 'city' => 'Douala'],
            [
                'country' => 'CM',
                'zones' => collect(self::SOLEX_DOUALA_ZONES)
                    ->map(fn ($quarters, $code) => ['code' => $code, 'label' => "Zone {$code}", 'quarters' => $quarters])
                    ->values()->all(),
                'vehicles' => collect(self::SOLEX_DOUALA_VEHICLES)->map(function ($vehicle) {
                    $prices = [];
                    foreach ($vehicle['rows'] as $from => $row) {
                        foreach ($row as $offset => $price) {
                            $prices[$from . '-' . ($from + $offset)] = $price;
                        }
                    }

                    return ['code' => $vehicle['code'], 'label' => $vehicle['label'], 'max_weight_kg' => $vehicle['max_weight_kg'],
                        'lead_time' => $vehicle['lead_time'], 'prices' => $prices];
                })->all(),
                'asso_commission' => 0,
                'is_active' => true,
            ]
        );

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
