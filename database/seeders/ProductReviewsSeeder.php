<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Product;
use App\Models\User;
use App\Models\ProductReview;

class ProductReviewsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Génération des avis produits...');

        // Récupérer tous les produits
        $products = Product::all();

        if ($products->isEmpty()) {
            $this->command->error('Aucun produit trouvé. Veuillez d\'abord créer des produits.');
            return;
        }

        // Récupérer tous les utilisateurs
        $users = User::where('role', '!=', 'admin')->get();

        if ($users->isEmpty()) {
            $this->command->error('Aucun utilisateur trouvé. Veuillez d\'abord créer des utilisateurs.');
            return;
        }

        $comments = [
            // Commentaires positifs (5 étoiles)
            [
                'rating' => 5,
                'comments' => [
                    'Excellent produit ! Je recommande vivement. Qualité au top !',
                    'Très satisfait de mon achat. Produit conforme à la description.',
                    'Produit de très bonne qualité. Livraison rapide. Parfait !',
                    'Je suis ravi ! Meilleur rapport qualité-prix. À acheter sans hésiter.',
                    'Produit exceptionnel ! Dépasse mes attentes. Merci beaucoup !',
                    'Super produit, exactement ce que je cherchais. Top qualité !',
                    'Impressionné par la qualité. Vraiment content de cet achat.',
                ]
            ],
            // Commentaires très bons (4 étoiles)
            [
                'rating' => 4,
                'comments' => [
                    'Bon produit dans l\'ensemble. Quelques petits détails à améliorer.',
                    'Satisfait de mon achat. Bonne qualité pour le prix.',
                    'Produit correct, fait le job. Je recommande.',
                    'Bien mais pourrait être mieux. Sinon rien à redire.',
                    'Bon rapport qualité-prix. Je suis content.',
                    'Produit de qualité. Livraison un peu longue mais ça va.',
                ]
            ],
            // Commentaires moyens (3 étoiles)
            [
                'rating' => 3,
                'comments' => [
                    'Produit moyen. Fait le travail mais sans plus.',
                    'Pas mal mais j\'attendais mieux pour le prix.',
                    'Correct sans être exceptionnel. Peut mieux faire.',
                    'Ça va, mais quelques défauts. Prix un peu élevé.',
                    'Produit standard. Rien d\'extraordinaire.',
                ]
            ],
            // Commentaires médiocres (2 étoiles)
            [
                'rating' => 2,
                'comments' => [
                    'Déçu par la qualité. Ne correspond pas à la description.',
                    'Pas terrible. J\'espérais mieux. Qualité moyenne.',
                    'Plusieurs défauts constatés. Prix trop élevé pour la qualité.',
                    'Pas satisfait. Le produit ne tient pas ses promesses.',
                ]
            ],
            // Commentaires négatifs (1 étoile)
            [
                'rating' => 1,
                'comments' => [
                    'Très déçu. Produit de mauvaise qualité. Je ne recommande pas.',
                    'Catastrophe ! Ne correspond pas du tout à la description.',
                    'Arnaque ! Qualité médiocre. À éviter absolument.',
                ]
            ],
        ];

        $totalReviews = 0;

        foreach ($products as $product) {
            // Nombre aléatoire d'avis par produit (entre 0 et 15)
            $reviewsCount = rand(0, 15);

            for ($i = 0; $i < $reviewsCount; $i++) {
                // Favoriser les bonnes notes (80% de notes 4-5 étoiles)
                $rand = rand(1, 100);
                if ($rand <= 50) {
                    // 50% de 5 étoiles
                    $ratingGroup = $comments[0];
                } elseif ($rand <= 80) {
                    // 30% de 4 étoiles
                    $ratingGroup = $comments[1];
                } elseif ($rand <= 90) {
                    // 10% de 3 étoiles
                    $ratingGroup = $comments[2];
                } elseif ($rand <= 97) {
                    // 7% de 2 étoiles
                    $ratingGroup = $comments[3];
                } else {
                    // 3% de 1 étoile
                    $ratingGroup = $comments[4];
                }

                $comment = $ratingGroup['comments'][array_rand($ratingGroup['comments'])];
                $user = $users->random();
                $isVerified = rand(1, 100) <= 70; // 70% d'achats vérifiés

                ProductReview::create([
                    'product_id' => $product->id,
                    'user_id' => $user->id,
                    'rating' => $ratingGroup['rating'],
                    'comment' => $comment,
                    'is_verified_purchase' => $isVerified,
                    'created_at' => now()->subDays(rand(0, 90)),
                ]);

                $totalReviews++;
            }
        }

        $this->command->info("\n✓ {$totalReviews} avis créés pour {$products->count()} produits!");

        // Afficher les statistiques
        $stats = [
            '5 étoiles' => ProductReview::where('rating', 5)->count(),
            '4 étoiles' => ProductReview::where('rating', 4)->count(),
            '3 étoiles' => ProductReview::where('rating', 3)->count(),
            '2 étoiles' => ProductReview::where('rating', 2)->count(),
            '1 étoile' => ProductReview::where('rating', 1)->count(),
        ];

        $this->command->info("\nRépartition des notes:");
        foreach ($stats as $label => $count) {
            $this->command->info("- {$label}: {$count}");
        }

        $avgRating = round(ProductReview::avg('rating'), 2);
        $this->command->info("\nNote moyenne globale: {$avgRating}/5");
    }
}
