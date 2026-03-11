<?php

namespace Database\Seeders;

use App\Models\Package;
use Illuminate\Database\Seeder;

class PackageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Génération des packages...');

        // ========================================
        // 1. PACKAGES DE STOCKAGE (3 niveaux)
        // ========================================
        $this->command->info('');
        $this->command->info('📦 Packages de Stockage:');

        $storagePackages = [
            [
                'type' => 'storage',
                'name' => 'Stockage Starter',
                'description' => 'Parfait pour démarrer votre activité. Stockez vos premières images de produits avec un espace de 300 Mo.',
                'price' => 1500,
                'duration_days' => 30,
                'storage_size_mb' => 300,
                'is_active' => true,
                'is_popular' => false,
                'order' => 1,
            ],
            [
                'type' => 'storage',
                'name' => 'Stockage Business',
                'description' => 'Pour les boutiques en pleine croissance. 1 Go d\'espace pour gérer un large catalogue de produits avec plusieurs images par article.',
                'price' => 3500,
                'duration_days' => 30,
                'storage_size_mb' => 1024, // 1 Go
                'is_active' => true,
                'is_popular' => true,
                'order' => 2,
            ],
            [
                'type' => 'storage',
                'name' => 'Stockage Premium',
                'description' => 'Solution professionnelle avec 5 Go d\'espace. Idéal pour les grandes boutiques avec catalogue étendu, vidéos promotionnelles et photos HD.',
                'price' => 8000,
                'duration_days' => 30,
                'storage_size_mb' => 5120, // 5 Go
                'is_active' => true,
                'is_popular' => false,
                'order' => 3,
            ],
        ];

        foreach ($storagePackages as $package) {
            Package::create($package);
            $this->command->info('  ✓ ' . $package['name'] . ' - ' . ($package['storage_size_mb'] >= 1024 ? ($package['storage_size_mb']/1024).'Go' : $package['storage_size_mb'].'Mo') . ' - ' . number_format($package['price']) . ' XOF/mois');
        }

        // ========================================
        // 2. PACKAGES DE BOOST/SPONSORING (3 niveaux)
        // ========================================
        $this->command->info('');
        $this->command->info('🚀 Packages de Boost Sponsoring:');

        $boostPackages = [
            [
                'type' => 'boost',
                'name' => 'Boost Local',
                'description' => 'Touchez jusqu\'à 1000 utilisateurs dans votre ville. Augmentez votre visibilité locale pendant 7 jours avec des placements prioritaires.',
                'price' => 2500,
                'duration_days' => 7,
                'reach_users' => 1000,
                'is_active' => true,
                'is_popular' => false,
                'order' => 1,
            ],
            [
                'type' => 'boost',
                'name' => 'Boost Régional',
                'description' => 'Étendez votre portée à 5000 utilisateurs dans votre région. 15 jours de visibilité renforcée pour booster vos ventes.',
                'price' => 6000,
                'duration_days' => 15,
                'reach_users' => 5000,
                'is_active' => true,
                'is_popular' => true,
                'order' => 2,
            ],
            [
                'type' => 'boost',
                'name' => 'Boost National',
                'description' => 'Visibilité maximale ! Touchez jusqu\'à 15000 utilisateurs partout au Cameroun. 30 jours de promotion intensive avec placement premium sur toutes les pages.',
                'price' => 15000,
                'duration_days' => 30,
                'reach_users' => 15000,
                'is_active' => true,
                'is_popular' => false,
                'order' => 3,
            ],
        ];

        foreach ($boostPackages as $package) {
            Package::create($package);
            $this->command->info('  ✓ ' . $package['name'] . ' - ' . number_format($package['reach_users']) . ' users - ' . number_format($package['price']) . ' XOF/' . $package['duration_days'] . 'j');
        }

        // ========================================
        // 3. PACKAGES DE CERTIFICATION (3 niveaux)
        // ========================================
        $this->command->info('');
        $this->command->info('✅ Packages de Certification:');

        $certificationPackages = [
            [
                'type' => 'certification',
                'name' => 'Certification Bronze',
                'description' => 'Devenez un vendeur vérifié. Gagnez la confiance des acheteurs avec le badge "Vendeur Certifié" visible sur vos annonces.',
                'price' => 5000,
                'duration_days' => 90, // 3 mois
                'benefits' => [
                    'Badge "Vendeur Certifié" visible',
                    'Profil mis en avant dans les recherches',
                    'Réponses prioritaires au support',
                ],
                'is_active' => true,
                'is_popular' => false,
                'order' => 1,
            ],
            [
                'type' => 'certification',
                'name' => 'Certification Silver',
                'description' => 'Certification professionnelle pour vendeurs sérieux. Tous les avantages Bronze + outils marketing et analyse de performance.',
                'price' => 12000,
                'duration_days' => 180, // 6 mois
                'benefits' => [
                    'Badge "Vendeur Professionnel"',
                    'Profil premium dans les recherches',
                    'Support prioritaire 7j/7',
                    'Statistiques avancées de ventes',
                    'Outils marketing inclus',
                    'Mise en avant automatique des produits',
                ],
                'is_active' => true,
                'is_popular' => true,
                'order' => 2,
            ],
            [
                'type' => 'certification',
                'name' => 'Certification Gold',
                'description' => 'Package VIP réservé aux vendeurs d\'élite. Visibilité maximale, accompagnement personnalisé et tous les privilèges de la plateforme.',
                'price' => 25000,
                'duration_days' => 365, // 1 an
                'benefits' => [
                    'Badge "Vendeur d\'Élite" exclusif',
                    'Placement premium garanti',
                    'Support VIP dédié 24/7',
                    'Manager de compte personnel',
                    'Rapports de performance détaillés',
                    'Campagnes marketing gratuites',
                    'Accès anticipé aux nouvelles fonctionnalités',
                    'Formation e-commerce offerte',
                    'Événements réseautage exclusifs',
                ],
                'is_active' => true,
                'is_popular' => false,
                'order' => 3,
            ],
        ];

        foreach ($certificationPackages as $package) {
            Package::create($package);
            $duration = $package['duration_days'] >= 365 ? ($package['duration_days']/365).'an' : ($package['duration_days']/30).'mois';
            $this->command->info('  ✓ ' . $package['name'] . ' - ' . count($package['benefits']) . ' avantages - ' . number_format($package['price']) . ' XOF/' . $duration);
        }

        // ========================================
        // Statistiques finales
        // ========================================
        $this->command->info('');
        $this->command->info('========================================');
        $this->command->info('✅ Packages générés avec succès!');
        $this->command->info('');
        $this->command->info('Statistiques:');
        $this->command->info('- Stockage: ' . Package::where('type', 'storage')->count() . ' packages');
        $this->command->info('- Boost: ' . Package::where('type', 'boost')->count() . ' packages');
        $this->command->info('- Certification: ' . Package::where('type', 'certification')->count() . ' packages');
        $this->command->info('- TOTAL: ' . Package::count() . ' packages');
        $this->command->info('- Packages populaires: ' . Package::where('is_popular', true)->count());
        $this->command->info('========================================');
    }
}
