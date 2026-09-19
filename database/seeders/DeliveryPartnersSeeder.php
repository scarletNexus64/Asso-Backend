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

    /**
     * SOLEX Douala — zones de couverture urbaine (légende de la grille) et position
     * APPROXIMATIVE de chaque quartier [lat, lng], à ajuster sur la carte de l'admin.
     */
    private const SOLEX_DOUALA_ZONES = [
        1 => ['Bali' => [4.0440, 9.6960], 'Bonapriso' => [4.0310, 9.6930], 'Bonanjo' => [4.0450, 9.6880],
              'Aéroport' => [4.0100, 9.7180], 'Deido' => [4.0640, 9.7080], 'New-Bell' => [4.0380, 9.7120]],
        2 => ['Akwa-Nord' => [4.0640, 9.7200], 'Mboppi' => [4.0530, 9.7130]],
        3 => ['Bonamoussadi' => [4.0940, 9.7420], 'Kotto' => [4.0790, 9.7490], 'Makèpè' => [4.0830, 9.7560],
              'Bonabéri' => [4.0770, 9.6630], 'Ndobo' => [4.0890, 9.6490]],
        4 => ['Zone Portuaire' => [4.0550, 9.6880], 'Essengué' => [4.0560, 9.7010], 'Bois des Singes' => [4.0200, 9.7060]],
        5 => ['Elf axe lourd' => [4.0330, 9.7440], 'Borne 10' => [4.0220, 9.7760], 'Nyassa' => [4.0410, 9.7560],
              'Nyalla' => [4.0100, 9.7700], 'Logbaba' => [4.0340, 9.7650], 'PK8' => [4.0300, 9.7800],
              'Cité des Palmiers' => [4.0500, 9.7400], 'Ndogbong' => [4.0610, 9.7450], 'Béedi' => [4.0450, 9.7690]],
        6 => ['Dakar' => [4.0340, 9.7250], 'Ndokoti' => [4.0450, 9.7350], 'BP Cité' => [4.0560, 9.7600], 'Ange Raphaël' => [4.0280, 9.7300]],
        7 => ['Logpom' => [4.0860, 9.7750], 'Lendi' => [4.0950, 9.7950], 'Logbessou' => [4.1000, 9.7850],
              'PK14' => [4.0700, 9.8150], 'Mbanguè' => [4.0750, 9.8000]],
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

        // Grille créée une seule fois : ensuite les prix, quartiers et positions se gèrent
        // dans l'admin (on ne complète que les positions manquantes).
        $zones = collect(self::SOLEX_DOUALA_ZONES)->map(fn ($quarters, $code) => [
            'code' => $code,
            'label' => "Zone {$code}",
            'quarters' => collect($quarters)->map(fn ($point, $name) => ['name' => $name, 'lat' => $point[0], 'lng' => $point[1]])->values()->all(),
        ])->values()->all();

        $grid = DeliveryCityGrid::where('deliverer_company_id', $solex->id)->where('city', 'Douala')->first();
        if (!$grid) {
            DeliveryCityGrid::create([
                'deliverer_company_id' => $solex->id,
                'city' => 'Douala',
                'country' => 'CM',
                'zones' => $zones,
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
            ]);
        } else {
            $seedPoints = collect($zones)->flatMap(fn ($z) => $z['quarters'])->keyBy('name');
            $grid->update(['zones' => collect($grid->zones)->map(function ($zone) use ($seedPoints) {
                $zone['quarters'] = collect(DeliveryCityGrid::quartersOf($zone))->map(fn ($q) => $q['lat'] === null && $seedPoints->has($q['name'])
                    ? $seedPoints[$q['name']] : $q)->all();

                return $zone;
            })->all()]);
        }

        // Boutiques de Douala rattachées à leur quartier (position sur leur carte).
        \App\Models\Shop::whereNotNull('latitude')->each(function (\App\Models\Shop $shop) {
            $shop->assignDeliveryQuarter();
            if ($shop->isDirty('quarter')) {
                $shop->saveQuietly();
            }
        });

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
