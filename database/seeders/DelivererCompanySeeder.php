<?php

namespace Database\Seeders;

use App\Models\DelivererCompany;
use App\Models\DeliveryZone;
use App\Models\DeliveryPricelist;
use App\Models\DelivererSyncCode;
use App\Models\DelivererCodeSync;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Hash;

class DelivererCompanySeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('');
        $this->command->info('========================================');
        $this->command->info('  Création des sociétés de livraison');
        $this->command->info('  Focus : Cameroun');
        $this->command->info('========================================');

        // ─── Create deliverer users first ───
        $deliverers = $this->createDelivererUsers();

        // ─── Companies ───
        $companies = [
            // ══════════════════════════════════════
            // 1. YANGO DELIVERY CAMEROUN
            // ══════════════════════════════════════
            [
                'name' => 'Yango Delivery Cameroun',
                'phone' => '+237 655 00 11 22',
                'email' => 'delivery@yango.cm',
                'description' => 'Filiale livraison de Yango, leader du transport urbain au Cameroun. Service de livraison express en moto et voiture dans les grandes villes. Suivi GPS en temps réel, assurance colis incluse. Livraison en 30 minutes à 2 heures selon la zone.',
                'logo_url' => 'https://images.unsplash.com/photo-1616432043562-3671ea2e5242?w=200&h=200&fit=crop',
                'zones' => [
                    [
                        'name' => 'Douala Centre-Ville',
                        'city' => 'Douala',
                        'center_latitude' => 4.0511,
                        'center_longitude' => 9.7679,
                        'pricing_type' => 'fixed',
                        'pricing_data' => ['price' => 1000],
                    ],
                    [
                        'name' => 'Douala Akwa - Bonapriso - Bonanjo',
                        'city' => 'Douala',
                        'center_latitude' => 4.0435,
                        'center_longitude' => 9.6966,
                        'pricing_type' => 'fixed',
                        'pricing_data' => ['price' => 1200],
                    ],
                    [
                        'name' => 'Douala Bonabéri - Deïdo',
                        'city' => 'Douala',
                        'center_latitude' => 4.0730,
                        'center_longitude' => 9.7150,
                        'pricing_type' => 'fixed',
                        'pricing_data' => ['price' => 1500],
                    ],
                    [
                        'name' => 'Douala PK - Ndokoti - Bépanda',
                        'city' => 'Douala',
                        'center_latitude' => 4.0620,
                        'center_longitude' => 9.7850,
                        'pricing_type' => 'weight_category',
                        'pricing_data' => [
                            'X-small' => 800,
                            '30 Deep' => 1200,
                            '50 Deep' => 1800,
                            '60 Deep' => 2500,
                            'Rainbow XL' => 4000,
                            'Pallet' => 8000,
                        ],
                    ],
                    [
                        'name' => 'Yaoundé Centre',
                        'city' => 'Yaoundé',
                        'center_latitude' => 3.8480,
                        'center_longitude' => 11.5021,
                        'pricing_type' => 'fixed',
                        'pricing_data' => ['price' => 1000],
                    ],
                    [
                        'name' => 'Yaoundé Bastos - Nlongkak',
                        'city' => 'Yaoundé',
                        'center_latitude' => 3.8750,
                        'center_longitude' => 11.5100,
                        'pricing_type' => 'fixed',
                        'pricing_data' => ['price' => 1500],
                    ],
                ],
                'sync_codes_count' => 3,
                'assign_deliverers' => [0, 1, 2], // indices dans $deliverers
            ],

            // ══════════════════════════════════════
            // 2. JUMIA LOGISTICS CAMEROUN
            // ══════════════════════════════════════
            [
                'name' => 'Jumia Logistics',
                'phone' => '+237 699 88 77 66',
                'email' => 'logistics@jumia.cm',
                'description' => 'Réseau logistique de Jumia au Cameroun. Infrastructure solide avec entrepôts à Douala et Yaoundé. Livraison standard (2-5 jours) et express (24h). Retour gratuit sous 7 jours. Points relais disponibles dans les quartiers principaux.',
                'logo_url' => 'https://images.unsplash.com/photo-1586528116311-ad8dd3c8310d?w=200&h=200&fit=crop',
                'zones' => [
                    [
                        'name' => 'Grand Douala',
                        'city' => 'Douala',
                        'center_latitude' => 4.0511,
                        'center_longitude' => 9.7679,
                        'pricing_type' => 'weight_category',
                        'pricing_data' => [
                            'X-small' => 500,
                            '30 Deep' => 1000,
                            '50 Deep' => 1500,
                            '60 Deep' => 2000,
                            'Rainbow XL' => 3500,
                            'Pallet' => 7500,
                        ],
                    ],
                    [
                        'name' => 'Grand Yaoundé',
                        'city' => 'Yaoundé',
                        'center_latitude' => 3.8480,
                        'center_longitude' => 11.5021,
                        'pricing_type' => 'weight_category',
                        'pricing_data' => [
                            'X-small' => 500,
                            '30 Deep' => 1000,
                            '50 Deep' => 1500,
                            '60 Deep' => 2200,
                            'Rainbow XL' => 3800,
                            'Pallet' => 8000,
                        ],
                    ],
                    [
                        'name' => 'Bafoussam',
                        'city' => 'Bafoussam',
                        'center_latitude' => 5.4737,
                        'center_longitude' => 10.4176,
                        'pricing_type' => 'fixed',
                        'pricing_data' => ['price' => 2500],
                    ],
                    [
                        'name' => 'Kribi',
                        'city' => 'Kribi',
                        'center_latitude' => 2.9400,
                        'center_longitude' => 9.9100,
                        'pricing_type' => 'fixed',
                        'pricing_data' => ['price' => 3000],
                    ],
                ],
                'sync_codes_count' => 2,
                'assign_deliverers' => [3, 4],
            ],

            // ══════════════════════════════════════
            // 3. GOZEM DELIVERY
            // ══════════════════════════════════════
            [
                'name' => 'Gozem Delivery',
                'phone' => '+237 677 33 44 55',
                'email' => 'delivery@gozem.cm',
                'description' => 'Service de livraison urbaine par Gozem. Réseau de coursiers moto rapides et fiables. Tarif transparent calculé au poids volumétrique. Idéal pour les petits et moyens colis. Couverture Douala, Yaoundé et Buéa. Application de suivi disponible.',
                'logo_url' => 'https://images.unsplash.com/photo-1558618666-fcd25c85f82e?w=200&h=200&fit=crop',
                'zones' => [
                    [
                        'name' => 'Douala Métropole',
                        'city' => 'Douala',
                        'center_latitude' => 4.0511,
                        'center_longitude' => 9.7679,
                        'pricing_type' => 'volumetric_weight',
                        'pricing_data' => [
                            'ranges' => [
                                ['min' => 0, 'max' => 3, 'price' => 500],
                                ['min' => 3.01, 'max' => 10, 'price' => 1000],
                                ['min' => 10.01, 'max' => 25, 'price' => 2000],
                                ['min' => 25.01, 'max' => 50, 'price' => 3500],
                                ['min' => 50.01, 'max' => 100, 'price' => 6000],
                            ],
                        ],
                    ],
                    [
                        'name' => 'Yaoundé Métropole',
                        'city' => 'Yaoundé',
                        'center_latitude' => 3.8480,
                        'center_longitude' => 11.5021,
                        'pricing_type' => 'volumetric_weight',
                        'pricing_data' => [
                            'ranges' => [
                                ['min' => 0, 'max' => 3, 'price' => 500],
                                ['min' => 3.01, 'max' => 10, 'price' => 1200],
                                ['min' => 10.01, 'max' => 25, 'price' => 2200],
                                ['min' => 25.01, 'max' => 50, 'price' => 3800],
                                ['min' => 50.01, 'max' => 100, 'price' => 6500],
                            ],
                        ],
                    ],
                    [
                        'name' => 'Buéa - Limbé',
                        'city' => 'Buéa',
                        'center_latitude' => 4.1560,
                        'center_longitude' => 9.2632,
                        'pricing_type' => 'fixed',
                        'pricing_data' => ['price' => 2000],
                    ],
                ],
                'sync_codes_count' => 2,
                'assign_deliverers' => [5, 6],
            ],

            // ══════════════════════════════════════
            // 4. SPEED MOTO COURSES
            // ══════════════════════════════════════
            [
                'name' => 'Speed Moto Courses',
                'phone' => '+237 690 12 34 56',
                'email' => 'contact@speedmoto.cm',
                'description' => 'Spécialiste de la livraison express par moto-taxi (bendskin) à Douala. Le plus rapide de la ville ! Livraison en moins de 45 minutes dans Douala Centre. Tarif fixe imbattable pour les petits colis. Service disponible 7j/7 de 6h à 22h. Paiement Mobile Money accepté.',
                'logo_url' => 'https://images.unsplash.com/photo-1558618666-fcd25c85f82e?w=200&h=200&fit=crop',
                'zones' => [
                    [
                        'name' => 'Douala Express Zone',
                        'city' => 'Douala',
                        'center_latitude' => 4.0511,
                        'center_longitude' => 9.7679,
                        'pricing_type' => 'fixed',
                        'pricing_data' => ['price' => 800],
                    ],
                    [
                        'name' => 'Douala Étendu (Logbessou - Makepe)',
                        'city' => 'Douala',
                        'center_latitude' => 4.0300,
                        'center_longitude' => 9.7350,
                        'pricing_type' => 'weight_category',
                        'pricing_data' => [
                            'X-small' => 500,
                            '30 Deep' => 800,
                            '50 Deep' => 1200,
                            '60 Deep' => 1800,
                            'Rainbow XL' => 3000,
                            'Pallet' => 6000,
                        ],
                    ],
                ],
                'sync_codes_count' => 2,
                'assign_deliverers' => [7, 8],
            ],

            // ══════════════════════════════════════
            // 5. CAMEROUN COLIS EXPRESS
            // ══════════════════════════════════════
            [
                'name' => 'Cameroun Colis Express',
                'phone' => '+237 222 33 44 55',
                'email' => 'info@camcolis.cm',
                'description' => 'Société de livraison intercity couvrant tout le triangle économique (Douala - Yaoundé - Bafoussam). Transport de colis entre villes avec points de dépôt/retrait. Livraison lourde et volumineuse. Entreprise fondée en 2019, partenaire de confiance des e-commerçants camerounais.',
                'logo_url' => 'https://images.unsplash.com/photo-1580674285054-bed31e145f59?w=200&h=200&fit=crop',
                'zones' => [
                    [
                        'name' => 'Douala ↔ Yaoundé',
                        'city' => 'Douala',
                        'center_latitude' => 4.0511,
                        'center_longitude' => 9.7679,
                        'pricing_type' => 'weight_category',
                        'pricing_data' => [
                            'X-small' => 1500,
                            '30 Deep' => 2500,
                            '50 Deep' => 3500,
                            '60 Deep' => 5000,
                            'Rainbow XL' => 8000,
                            'Pallet' => 15000,
                        ],
                    ],
                    [
                        'name' => 'Douala ↔ Bafoussam',
                        'city' => 'Douala',
                        'center_latitude' => 5.4737,
                        'center_longitude' => 10.4176,
                        'pricing_type' => 'weight_category',
                        'pricing_data' => [
                            'X-small' => 2000,
                            '30 Deep' => 3000,
                            '50 Deep' => 4500,
                            '60 Deep' => 6000,
                            'Rainbow XL' => 10000,
                            'Pallet' => 18000,
                        ],
                    ],
                    [
                        'name' => 'Yaoundé ↔ Bafoussam',
                        'city' => 'Yaoundé',
                        'center_latitude' => 3.8480,
                        'center_longitude' => 11.5021,
                        'pricing_type' => 'volumetric_weight',
                        'pricing_data' => [
                            'ranges' => [
                                ['min' => 0, 'max' => 5, 'price' => 2000],
                                ['min' => 5.01, 'max' => 15, 'price' => 3500],
                                ['min' => 15.01, 'max' => 30, 'price' => 5500],
                                ['min' => 30.01, 'max' => 60, 'price' => 8000],
                                ['min' => 60.01, 'max' => 200, 'price' => 15000],
                            ],
                        ],
                    ],
                ],
                'sync_codes_count' => 2,
                'assign_deliverers' => [9],
            ],

            // ══════════════════════════════════════
            // 6. KWIK DELIVERY CAMEROUN
            // ══════════════════════════════════════
            [
                'name' => 'Kwik Delivery Cameroun',
                'phone' => '+237 691 77 88 99',
                'email' => 'cameroun@kwik.cm',
                'description' => 'Kwik Delivery - la livraison en un clic. Application mobile de livraison à la demande. Coursiers professionnels en moto et véhicule. Tarification transparente au kilomètre. Livraison de repas, courses, colis et documents. Service premium avec assurance complète.',
                'logo_url' => 'https://images.unsplash.com/photo-1526367790999-0150786686a2?w=200&h=200&fit=crop',
                'zones' => [
                    [
                        'name' => 'Douala Intra-muros',
                        'city' => 'Douala',
                        'center_latitude' => 4.0511,
                        'center_longitude' => 9.7679,
                        'pricing_type' => 'fixed',
                        'pricing_data' => ['price' => 1500],
                    ],
                    [
                        'name' => 'Yaoundé Intra-muros',
                        'city' => 'Yaoundé',
                        'center_latitude' => 3.8480,
                        'center_longitude' => 11.5021,
                        'pricing_type' => 'fixed',
                        'pricing_data' => ['price' => 1500],
                    ],
                    [
                        'name' => 'Bamenda',
                        'city' => 'Bamenda',
                        'center_latitude' => 5.9597,
                        'center_longitude' => 10.1459,
                        'pricing_type' => 'fixed',
                        'pricing_data' => ['price' => 2000],
                    ],
                    [
                        'name' => 'Garoua',
                        'city' => 'Garoua',
                        'center_latitude' => 9.3014,
                        'center_longitude' => 13.3975,
                        'pricing_type' => 'fixed',
                        'pricing_data' => ['price' => 2500],
                    ],
                ],
                'sync_codes_count' => 2,
                'assign_deliverers' => [10, 11],
            ],
        ];

        $totalZones = 0;
        $totalCodes = 0;
        $totalSyncs = 0;

        foreach ($companies as $companyData) {
            $zones = $companyData['zones'];
            $syncCodesCount = $companyData['sync_codes_count'];
            $assignDeliverers = $companyData['assign_deliverers'];
            unset($companyData['zones'], $companyData['sync_codes_count'], $companyData['assign_deliverers']);

            // Download logo
            $logoPath = null;
            if (!empty($companyData['logo_url'])) {
                $logoPath = $this->downloadLogo($companyData['logo_url'], $companyData['name']);
                unset($companyData['logo_url']);
                if ($logoPath) {
                    $companyData['logo'] = $logoPath;
                }
            } else {
                unset($companyData['logo_url']);
            }

            // Create company
            $company = DelivererCompany::updateOrCreate(
                ['name' => $companyData['name']],
                $companyData
            );

            $this->command->info('');
            $this->command->info("🚚 {$company->name}");
            $this->command->info("   📞 {$company->phone} | 📧 {$company->email}");
            if ($logoPath) {
                $this->command->info("   🖼️  Logo téléchargé");
            }

            // Create zones & pricelists
            foreach ($zones as $zoneData) {
                $pricingType = $zoneData['pricing_type'];
                $pricingData = $zoneData['pricing_data'];
                unset($zoneData['pricing_type'], $zoneData['pricing_data']);

                $zone = DeliveryZone::updateOrCreate(
                    [
                        'deliverer_company_id' => $company->id,
                        'name' => $zoneData['name'],
                    ],
                    array_merge($zoneData, ['deliverer_company_id' => $company->id])
                );

                DeliveryPricelist::updateOrCreate(
                    ['delivery_zone_id' => $zone->id],
                    [
                        'pricing_type' => $pricingType,
                        'pricing_data' => $pricingData,
                    ]
                );

                $pricingLabel = match ($pricingType) {
                    'fixed' => "fixe " . number_format($pricingData['price'], 0, ',', ' ') . " XAF",
                    'weight_category' => "par poids (" . number_format($pricingData['X-small'], 0, ',', ' ') . " - " . number_format($pricingData['Pallet'], 0, ',', ' ') . " XAF)",
                    'volumetric_weight' => "volumétrique (" . count($pricingData['ranges']) . " tranches)",
                    default => $pricingType,
                };

                $this->command->info("   📍 {$zone->name} ({$zone->city}) → {$pricingLabel}");
                $totalZones++;
            }

            // Generate sync codes
            $generatedCodes = [];
            for ($i = 0; $i < $syncCodesCount; $i++) {
                $syncCode = DelivererSyncCode::generateSyncCode();
                $sentVia = match ($i) {
                    0 => 'whatsapp',
                    1 => 'sms',
                    default => 'all',
                };

                $syncCodeModel = DelivererSyncCode::create([
                    'user_id' => null,
                    'company_id' => $company->id,
                    'sync_code' => $syncCode,
                    'sent_via' => $sentVia,
                    'sent_at' => now(),
                    'expires_at' => now()->addDays(90),
                ]);

                $generatedCodes[] = $syncCodeModel;
                $this->command->info("   🔑 Code: {$syncCode} (via {$sentVia}, expire dans 90j)");
                $totalCodes++;
            }

            // Sync deliverers to this company
            foreach ($assignDeliverers as $delivererIndex) {
                if (isset($deliverers[$delivererIndex]) && !empty($generatedCodes)) {
                    $deliverer = $deliverers[$delivererIndex];
                    $codeToUse = $generatedCodes[0]; // Use first code

                    DelivererCodeSync::updateOrCreate(
                        [
                            'user_id' => $deliverer->id,
                            'company_id' => $company->id,
                        ],
                        [
                            'sync_code_id' => $codeToUse->id,
                            'is_active' => true,
                            'is_banned' => false,
                            'synced_at' => now()->subDays(rand(1, 30)),
                        ]
                    );

                    $this->command->info("   👤 Livreur sync: {$deliverer->first_name} {$deliverer->last_name} ({$deliverer->phone})");
                    $totalSyncs++;
                }
            }
        }

        $this->command->info('');
        $this->command->info('========================================');
        $this->command->info("✅ Résumé :");
        $this->command->info("   🚚 " . count($companies) . " sociétés de livraison");
        $this->command->info("   📍 {$totalZones} zones de livraison");
        $this->command->info("   🔑 {$totalCodes} codes de synchronisation");
        $this->command->info("   👤 {$totalSyncs} livreurs synchronisés");
        $this->command->info('========================================');
    }

    /**
     * Create deliverer users for syncing
     */
    private function createDelivererUsers(): array
    {
        $delivererData = [
            ['first_name' => 'Jean-Pierre', 'last_name' => 'Mbarga', 'phone' => '+237 690 01 01 01', 'gender' => 'male', 'country' => 'Cameroun'],
            ['first_name' => 'Paul', 'last_name' => 'Nkoulou', 'phone' => '+237 677 02 02 02', 'gender' => 'male', 'country' => 'Cameroun'],
            ['first_name' => 'Émile', 'last_name' => 'Fotso', 'phone' => '+237 699 03 03 03', 'gender' => 'male', 'country' => 'Cameroun'],
            ['first_name' => 'Sandrine', 'last_name' => 'Ngo Biyong', 'phone' => '+237 655 04 04 04', 'gender' => 'female', 'country' => 'Cameroun'],
            ['first_name' => 'Michel', 'last_name' => 'Tchakounte', 'phone' => '+237 691 05 05 05', 'gender' => 'male', 'country' => 'Cameroun'],
            ['first_name' => 'Rodrigue', 'last_name' => 'Kamdem', 'phone' => '+237 677 06 06 06', 'gender' => 'male', 'country' => 'Cameroun'],
            ['first_name' => 'Blaise', 'last_name' => 'Nkwenti', 'phone' => '+237 690 07 07 07', 'gender' => 'male', 'country' => 'Cameroun'],
            ['first_name' => 'Aristide', 'last_name' => 'Moungang', 'phone' => '+237 699 08 08 08', 'gender' => 'male', 'country' => 'Cameroun'],
            ['first_name' => 'Gaëlle', 'last_name' => 'Ndam', 'phone' => '+237 655 09 09 09', 'gender' => 'female', 'country' => 'Cameroun'],
            ['first_name' => 'Thierry', 'last_name' => 'Tagne', 'phone' => '+237 691 10 10 10', 'gender' => 'male', 'country' => 'Cameroun'],
            ['first_name' => 'Franck', 'last_name' => 'Ngono', 'phone' => '+237 677 11 11 11', 'gender' => 'male', 'country' => 'Cameroun'],
            ['first_name' => 'Cédric', 'last_name' => 'Eyinga', 'phone' => '+237 690 12 12 12', 'gender' => 'male', 'country' => 'Cameroun'],
        ];

        $users = [];
        foreach ($delivererData as $data) {
            $user = User::updateOrCreate(
                ['phone' => $data['phone']],
                [
                    'first_name' => $data['first_name'],
                    'last_name' => $data['last_name'],
                    'email' => strtolower($data['first_name']) . '.' . strtolower(str_replace(' ', '', $data['last_name'])) . '@asso-delivery.cm',
                    'password' => Hash::make('Livreur@2026'),
                    'role' => 'livreur',
                    'gender' => $data['gender'],
                    'country' => $data['country'],
                    'email_verified_at' => now(),
                    'referral_code' => 'LIV' . strtoupper(substr(md5($data['phone']), 0, 6)),
                ]
            );
            $users[] = $user;
        }

        return $users;
    }

    /**
     * Download logo from URL
     */
    private function downloadLogo(string $url, string $companyName): ?string
    {
        try {
            $response = Http::timeout(15)->get($url);

            if ($response->successful()) {
                $filename = 'deliverers/' . \Illuminate\Support\Str::slug($companyName) . '-' . uniqid() . '.jpg';
                Storage::disk('public')->put($filename, $response->body());
                return $filename;
            }
        } catch (\Exception $e) {
            $this->command->warn("   ⚠️  Logo non téléchargé: {$e->getMessage()}");
        }

        return null;
    }
}
