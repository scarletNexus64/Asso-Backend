<?php

namespace Database\Seeders;

use App\Models\Shop;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ShopSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get all vendeurs (sellers)
        $sellers = User::where('role', 'vendeur')->get();

        $shops = [
            [
                'name' => 'Mode Africaine Élégance',
                'description' => 'Boutique spécialisée dans les vêtements africains modernes et traditionnels. Nous offrons une large gamme de tissus wax, bazin, et pagnes de qualité supérieure. Nos créations allient tradition et modernité pour une élégance intemporelle.',
                'address' => 'Marché Dantokpa, Cotonou',
                'latitude' => 6.3654,
                'longitude' => 2.4183,
            ],
            [
                'name' => 'Fashion Urban Bénin',
                'description' => 'Votre destination pour les dernières tendances de la mode urbaine. Streetwear, sportswear, et vêtements casual pour homme et femme. Collections renouvelées régulièrement pour rester à la pointe de la mode.',
                'address' => 'Rue des Cheminots, Cotonou',
                'latitude' => 6.3702,
                'longitude' => 2.4281,
            ],
            [
                'name' => 'Chic & Classe Premium',
                'description' => 'Boutique haut de gamme proposant des vêtements de luxe pour occasions spéciales. Robes de soirée, costumes sur mesure, et accessoires raffinés. Service personnalisé et conseil stylistique inclus.',
                'address' => 'Akpakpa, Cotonou',
                'latitude' => 6.3478,
                'longitude' => 2.4398,
            ],
            [
                'name' => 'Style Confort Quotidien',
                'description' => 'Spécialiste du prêt-à-porter confortable et abordable. Vêtements décontractés, tenues professionnelles, et essentiels du quotidien. Qualité et confort à prix accessible pour toute la famille.',
                'address' => 'Ganhi, Cotonou',
                'latitude' => 6.3589,
                'longitude' => 2.4512,
            ],
            [
                'name' => 'Tendances Jeunes',
                'description' => 'La boutique des jeunes branchés ! Mode streetwear, sportswear tendance, sneakers exclusives. Collaborations avec des marques internationales et créateurs locaux pour un style unique et audacieux.',
                'address' => 'Cadjehoun, Cotonou',
                'latitude' => 6.3521,
                'longitude' => 2.4089,
            ],
        ];

        foreach ($shops as $index => $shopData) {
            if (isset($sellers[$index])) {
                Shop::create([
                    'user_id' => $sellers[$index]->id,
                    'name' => $shopData['name'],
                    'slug' => Str::slug($shopData['name']),
                    'description' => $shopData['description'],
                    'shop_link' => Str::slug($shopData['name']) . '.asso.com',
                    'address' => $shopData['address'],
                    'latitude' => $shopData['latitude'],
                    'longitude' => $shopData['longitude'],
                    'status' => 'active',
                ]);
            }
        }
    }
}
