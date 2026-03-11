<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
use App\Models\Shop;
use App\Models\Category;
use App\Models\Subcategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get necessary data
        $sellers = User::where('role', 'vendeur')->get();
        $shops = Shop::all();

        // Get Fashion category and its subcategories
        $fashionCategory = Category::where('slug', 'mode-vetements')->first();

        if (!$fashionCategory || $shops->isEmpty() || $sellers->isEmpty()) {
            $this->command->error('❌ Please run CategorySeeder, UserSeeder and ShopSeeder first!');
            return;
        }

        $fashionSubcategories = Subcategory::where('category_id', $fashionCategory->id)->get();

        // Available images
        $availableImages = ['storage/products/img.jpg', 'storage/products/img2.jpg', 'storage/products/img3.jpg'];

        // Product templates for men's clothing
        $mensProducts = [
            [
                'name' => 'Polo Premium Coton Piqué',
                'description' => 'Polo élégant en 100% coton piqué de haute qualité. Coupe moderne et confortable, parfait pour un look casual chic. Disponible en plusieurs coloris : bleu marine, gris, bleu ciel et beige. Col côtelé avec boutons nacrés, manches courtes avec finitions soignées. Idéal pour le travail ou les sorties décontractées.',
                'price' => 12500,
                'stock' => 45,
                'type' => 'article',
                'price_type' => 'fixed',
                'subcategory_name' => 't-shirts-polos',
            ],
            [
                'name' => 'Chemise Oxford Manches Longues',
                'description' => 'Chemise classique en tissu Oxford de qualité supérieure. Coupe droite, col boutonné, poche poitrine. Tissu respirant et résistant au froissage. Parfaite pour le bureau ou les occasions formelles. Disponible en blanc, bleu ciel et rayures fines. Facile d\'entretien, repassage minimal requis.',
                'price' => 18500,
                'stock' => 32,
                'type' => 'article',
                'price_type' => 'fixed',
                'subcategory_name' => 'vetements-hommes',
            ],
            [
                'name' => 'Pantalon Chino Stretch Slim Fit',
                'description' => 'Pantalon chino moderne avec une touche de stretch pour un confort optimal. Coupe slim ajustée, taille mi-haute, poches latérales et arrière. Tissu doux et résistant, parfait pour un look smart casual. Disponible en beige, bleu marine, kaki et gris anthracite. S\'associe parfaitement avec chemises et polos.',
                'price' => 22000,
                'stock' => 28,
                'type' => 'article',
                'price_type' => 'fixed',
                'subcategory_name' => 'vetements-hommes',
            ],
            [
                'name' => 'Jean Denim Brut Regular Fit',
                'description' => 'Jean classique en denim brut de qualité premium. Coupe regular confortable, 5 poches traditionnelles, coutures renforcées pour une durabilité maximale. Denim 100% coton avec un toucher authentique. Couleur indigo profond qui patine joliment avec le temps. Un indispensable de la garde-robe masculine.',
                'price' => 25000,
                'stock' => 35,
                'type' => 'article',
                'price_type' => 'fixed',
                'subcategory_name' => 'vetements-hommes',
            ],
            [
                'name' => 'Costume 2 Pièces Laine Premium',
                'description' => 'Ensemble costume deux pièces en laine mélangée de haute qualité. Veste cintrée avec revers crantés, deux boutons, poches passepoilées. Pantalon assorti avec plis, ceinture ajustable. Doublure en satin pour un confort optimal. Parfait pour mariages, événements formels et rendez-vous professionnels importants.',
                'min_price' => 85000,
                'max_price' => 120000,
                'price_type' => 'variable',
                'stock' => 15,
                'type' => 'article',
                'subcategory_name' => 'vetements-hommes',
            ],
            [
                'name' => 'Veste Légère Mi-Saison',
                'description' => 'Veste casual légère idéale pour les journées fraîches. Tissu déperlant, fermeture éclair centrale, poches latérales zippées. Col montant avec capuche escamotable. Design moderne et épuré. Disponible en bleu marine, kaki et noir. Parfaite pour la mi-saison, protège du vent et de la pluie légère.',
                'price' => 32000,
                'stock' => 20,
                'type' => 'article',
                'price_type' => 'fixed',
                'subcategory_name' => 'vetements-hommes',
            ],
            [
                'name' => 'T-Shirt Col V Basique Pack x3',
                'description' => 'Pack de 3 t-shirts essentiels à col V en coton jersey doux. Coupe moderne ni trop ajustée ni trop ample. Finitions soignées, coutures flatlock pour éviter les irritations. Coloris assortis : blanc, noir, gris chiné. Idéaux à porter seuls ou en sous-vêtement. Lavage machine, séchage rapide.',
                'price' => 15000,
                'stock' => 50,
                'type' => 'article',
                'price_type' => 'fixed',
                'subcategory_name' => 't-shirts-polos',
            ],
            [
                'name' => 'Chemise Carreaux Flanelle',
                'description' => 'Chemise décontractée en flanelle douce à carreaux. Parfaite pour un look décontracté et chaleureux. Motif Buffalo check classique, boutons en nacre, deux poches plaquées. Tissu épais et confortable, idéal pour l\'automne. Disponible en combinaisons bleu-blanc et gris-blanc. Coupe regular confortable.',
                'price' => 16500,
                'stock' => 25,
                'type' => 'article',
                'price_type' => 'fixed',
                'subcategory_name' => 'vetements-hommes',
            ],
        ];

        // Product templates for women's clothing
        $womensProducts = [
            [
                'name' => 'Robe Midi Élégante Fluide',
                'description' => 'Robe midi élégante en tissu fluide et léger. Coupe évasée flatteuse, ceinture à la taille pour marquer la silhouette. Manches 3/4 avec finitions délicates. Parfaite pour les occasions spéciales, soirées ou cérémonies. Disponible en bordeaux, bleu roi, vert émeraude et noir. Doublure intérieure pour un confort optimal.',
                'price' => 28000,
                'stock' => 22,
                'type' => 'article',
                'price_type' => 'fixed',
                'subcategory_name' => 'vetements-femmes',
            ],
            [
                'name' => 'Chemisier Satin Col Lavallière',
                'description' => 'Chemisier sophistiqué en satin fluide avec col lavallière élégant. Coupe ample et confortable, manches longues avec poignets boutonnés. Tissu doux au toucher avec un joli tombé. Parfait pour le bureau ou les sorties élégantes. Coloris disponibles : blanc cassé, rose poudré, bleu ciel. Se marie parfaitement avec pantalons et jupes.',
                'price' => 19500,
                'stock' => 30,
                'type' => 'article',
                'price_type' => 'fixed',
                'subcategory_name' => 'vetements-femmes',
            ],
            [
                'name' => 'Jupe Plissée Longueur Genou',
                'description' => 'Jupe plissée élégante en tissu léger et fluide. Longueur genou, taille élastique avec ceinture ajustable. Plissé permanent qui ne nécessite pas de repassage. Doublure opaque pour une couverture parfaite. Idéale pour le travail ou les sorties. Couleurs : noir, bleu marine, camel, bordeaux. Très polyvalente et facile à assortir.',
                'price' => 18000,
                'stock' => 28,
                'type' => 'article',
                'price_type' => 'fixed',
                'subcategory_name' => 'vetements-femmes',
            ],
            [
                'name' => 'Pantalon Tailleur Cigarette',
                'description' => 'Pantalon tailleur coupe cigarette ultra chic. Taille haute, jambes fuselées, pinces pour un ajustement parfait. Tissu stretch de qualité pour confort et liberté de mouvement. Poches latérales et fausses poches arrière. Parfait pour un look professionnel élégant. Disponible en noir, gris anthracite et bleu marine.',
                'price' => 24000,
                'stock' => 25,
                'type' => 'article',
                'price_type' => 'fixed',
                'subcategory_name' => 'vetements-femmes',
            ],
            [
                'name' => 'Ensemble Wax Traditionnel Sur Mesure',
                'description' => 'Magnifique ensemble en tissu wax authentique confectionné sur mesure. Comprend une jupe longue évasée et un haut ajusté avec détails brodés. Motifs africains colorés et vibrants, tissu de qualité supérieure 100% coton. Coupe flatteuse qui met en valeur la silhouette. Idéal pour mariages, baptêmes et événements traditionnels. Possibilité de personnalisation des motifs.',
                'min_price' => 45000,
                'max_price' => 75000,
                'price_type' => 'variable',
                'stock' => 12,
                'type' => 'service',
                'subcategory_name' => 'vetements-femmes',
            ],
            [
                'name' => 'Top Dentelle Manches Courtes',
                'description' => 'Top raffiné en dentelle délicate avec doublure opaque. Manches courtes, encolure arrondie, finitions soignées. Dentelle florale féminine et élégante. Parfait pour les soirées ou occasions spéciales. Se porte facilement avec jupe ou pantalon. Couleurs : blanc, noir, rose poudré, champagne. Lavage délicat recommandé.',
                'price' => 16000,
                'stock' => 32,
                'type' => 'article',
                'price_type' => 'fixed',
                'subcategory_name' => 'vetements-femmes',
            ],
            [
                'name' => 'Jean Mom Fit Taille Haute',
                'description' => 'Jean tendance coupe mom fit avec taille haute. Style vintage revisité, jambes droites et relâchées. Denim confortable en coton stretch. 5 poches classiques, fermeture bouton. Couleur bleu délavé authentique. Parfait pour un look casual chic. S\'associe avec crop tops, chemises rentrées ou pulls oversize.',
                'price' => 23000,
                'stock' => 30,
                'type' => 'article',
                'price_type' => 'fixed',
                'subcategory_name' => 'vetements-femmes',
            ],
            [
                'name' => 'Robe Traditionnelle Bazin Riche',
                'description' => 'Élégante robe en bazin riche brodé avec motifs traditionnels. Coupe boubou moderne et flatteuse, broderies dorées raffinées. Tissu damassé de haute qualité avec finitions impeccables. Parfaite pour grandes occasions : mariages, cérémonies, fêtes traditionnelles. Disponible en plusieurs couleurs avec broderies assorties. Confection artisanale soignée.',
                'min_price' => 65000,
                'max_price' => 95000,
                'price_type' => 'variable',
                'stock' => 8,
                'type' => 'article',
                'subcategory_name' => 'vetements-femmes',
            ],
        ];

        // Product templates for sportswear
        $sportswearProducts = [
            [
                'name' => 'Ensemble Jogging 2 Pièces',
                'description' => 'Ensemble jogging confortable comprenant veste zippée et pantalon coordonné. Tissu technique respirant qui évacue l\'humidité. Poches zippées sécurisées, bas élastiqués. Design moderne avec bandes latérales contrastées. Idéal pour le sport, les entraînements ou le casual wear. Disponible en noir, gris, bleu marine. Coupe regular unisexe.',
                'price' => 28000,
                'stock' => 40,
                'type' => 'article',
                'price_type' => 'fixed',
                'subcategory_name' => 'sports-loisirs',
            ],
            [
                'name' => 'Legging Sport Haute Performance',
                'description' => 'Legging technique haute performance pour le sport intensif. Taille haute gainante, tissu compressif qui soutient les muscles. Technologie anti-transpiration et séchage rapide. Coutures plates pour éviter les frottements. Poche cachée pour smartphone. Parfait pour yoga, fitness, running. Tissu opaque même en squat. Coloris : noir, gris chiné, bordeaux.',
                'price' => 18500,
                'stock' => 35,
                'type' => 'article',
                'price_type' => 'fixed',
                'subcategory_name' => 'sports-loisirs',
            ],
            [
                'name' => 'T-Shirt Technique Running',
                'description' => 'T-shirt technique spécialement conçu pour la course à pied. Tissu ultra-léger et respirant avec zones mesh stratégiques. Technologie anti-odeur et évacuation rapide de la transpiration. Coupe athlétique, coutures flatlock. Bandes réfléchissantes pour visibilité nocturne. Disponible en plusieurs coloris vifs. Idéal pour running, trail, fitness.',
                'price' => 12000,
                'stock' => 45,
                'type' => 'article',
                'price_type' => 'fixed',
                'subcategory_name' => 'sports-loisirs',
            ],
            [
                'name' => 'Short Sport Multi-Poches',
                'description' => 'Short sportif pratique avec multiples poches fonctionnelles. Tissu léger résistant à l\'eau, ceinture élastique avec cordon ajustable. Poches latérales profondes, poche arrière zippée. Doublure mesh intégrée. Parfait pour le sport, les activités outdoor ou le casual. Longueur au-dessus du genou. Coloris : noir, bleu marine, kaki, gris.',
                'price' => 14500,
                'stock' => 38,
                'type' => 'article',
                'price_type' => 'fixed',
                'subcategory_name' => 'sports-loisirs',
            ],
        ];

        // Product templates for shoes
        $shoesProducts = [
            [
                'name' => 'Sneakers Urbaines Tendance',
                'description' => 'Sneakers modernes au design épuré et contemporain. Tige en cuir synthétique de qualité, semelle en caoutchouc antidérapante. Intérieur matelassé pour un confort maximal. Système de laçage classique, languette rembourrée. Disponibles en blanc, noir, gris. Parfaites pour un look streetwear ou casual chic. S\'accordent avec tout type de tenue.',
                'price' => 32000,
                'stock' => 30,
                'type' => 'article',
                'price_type' => 'fixed',
                'subcategory_name' => 'chaussures',
            ],
            [
                'name' => 'Chaussures Classiques Derby',
                'description' => 'Chaussures derby élégantes en cuir véritable. Finitions soignées, coutures apparentes, semelle en cuir. Laçage ouvert style derby traditionnel. Intérieur en cuir pour une respirabilité optimale. Parfaites pour occasions formelles, bureau, cérémonies. Disponibles en noir et marron foncé. Confort assuré même après plusieurs heures de port.',
                'price' => 42000,
                'stock' => 18,
                'type' => 'article',
                'price_type' => 'fixed',
                'subcategory_name' => 'chaussures',
            ],
            [
                'name' => 'Sandales Cuir Confort',
                'description' => 'Sandales en cuir véritable ultra confortables. Semelle anatomique qui épouse la forme du pied, bride réglable avec boucle. Dessus en cuir souple et résistant. Semelle antidérapante en caoutchouc. Idéales pour l\'été, la plage ou un usage quotidien. Disponibles en marron, noir, tan. Design intemporel et durable.',
                'price' => 18000,
                'stock' => 25,
                'type' => 'article',
                'price_type' => 'fixed',
                'subcategory_name' => 'chaussures',
            ],
        ];

        // Product templates for accessories
        $accessoriesProducts = [
            [
                'name' => 'Sac à Main Cuir Élégant',
                'description' => 'Sac à main sophistiqué en cuir synthétique haute qualité. Design intemporel avec compartiments multiples. Poche intérieure zippée pour objets de valeur, poches plaquées pour téléphone et accessoires. Bandoulière ajustable amovible, poignées renforcées. Fermeture magnétique sécurisée. Parfait pour le travail ou les sorties. Coloris : noir, camel, bordeaux.',
                'price' => 25000,
                'stock' => 20,
                'type' => 'article',
                'price_type' => 'fixed',
                'subcategory_name' => 'sacs-bagages',
            ],
            [
                'name' => 'Ceinture Cuir Réversible',
                'description' => 'Ceinture de qualité en cuir véritable réversible. Boucle métallique argentée ou dorée au choix, système de retournement pratique pour deux looks en un. Largeur classique 3,5cm, longueur ajustable. Cuir souple et durable. Parfaite pour pantalons formels et jeans. Réversible noir/marron ou noir/bleu marine. Excellent rapport qualité-prix.',
                'price' => 12000,
                'stock' => 35,
                'type' => 'article',
                'price_type' => 'fixed',
                'subcategory_name' => 'bijoux-montres',
            ],
            [
                'name' => 'Montre Analogique Élégante',
                'description' => 'Montre élégante avec cadran analogique minimaliste. Boîtier en acier inoxydable, verre minéral résistant aux rayures. Bracelet en cuir véritable confortable. Mouvement à quartz précis, étanche 3 ATM. Index chiffres romains, aiguilles lumineuses. Design classique qui traverse les modes. Disponible avec cadran blanc, noir ou bleu. Coffret cadeau inclus.',
                'min_price' => 15000,
                'max_price' => 28000,
                'price_type' => 'variable',
                'stock' => 25,
                'type' => 'article',
                'subcategory_name' => 'bijoux-montres',
            ],
            [
                'name' => 'Lunettes de Soleil Polarisées',
                'description' => 'Lunettes de soleil style aviateur avec verres polarisés UV400. Protection totale contre les rayons UV nocifs. Monture légère en métal avec branches flexibles. Verres anti-reflet et anti-rayures. Réduction de l\'éblouissement pour conduite et activités outdoor. Étui rigide et chiffon microfibre inclus. Coloris monture : or, argent, noir.',
                'price' => 18500,
                'stock' => 30,
                'type' => 'article',
                'price_type' => 'fixed',
                'subcategory_name' => 'bijoux-montres',
            ],
        ];

        // Combine all products
        $allProducts = array_merge(
            $mensProducts,
            $womensProducts,
            $sportswearProducts,
            $shoesProducts,
            $accessoriesProducts
        );

        $productCount = 0;

        // Create products
        foreach ($allProducts as $productData) {
            foreach ($shops as $shop) {
                // Create 2-3 variations per product per shop
                $variations = rand(2, 3);

                for ($i = 0; $i < $variations; $i++) {
                    $variation = $i + 1;
                    $productName = $productData['name'] . ($variations > 1 ? " - Variation {$variation}" : '');

                    // Find subcategory
                    $subcategory = null;
                    if (isset($productData['subcategory_name'])) {
                        $subcategory = Subcategory::where('slug', $productData['subcategory_name'])->first();
                    }

                    if (!$subcategory) {
                        $subcategory = $fashionSubcategories->random();
                    }

                    $product = Product::create([
                        'user_id' => $shop->user_id,
                        'shop_id' => $shop->id,
                        'category_id' => $fashionCategory->id,
                        'subcategory_id' => $subcategory->id,
                        'name' => $productName,
                        'slug' => Str::slug($productName) . '-' . uniqid(),
                        'description' => $productData['description'],
                        'price' => $productData['price'] ?? null,
                        'min_price' => $productData['min_price'] ?? null,
                        'max_price' => $productData['max_price'] ?? null,
                        'price_type' => $productData['price_type'],
                        'type' => $productData['type'],
                        'stock' => $productData['stock'] + rand(-10, 10),
                        'status' => 'active',
                    ]);

                    // Add multiple images (2-3 images per product)
                    $numImages = rand(2, 3);
                    $selectedImages = array_rand(array_flip($availableImages), $numImages);

                    if (!is_array($selectedImages)) {
                        $selectedImages = [$selectedImages];
                    }

                    foreach ($selectedImages as $index => $imagePath) {
                        ProductImage::create([
                            'product_id' => $product->id,
                            'image_path' => $imagePath,
                            'is_primary' => $index === 0,
                            'order' => $index,
                        ]);
                    }

                    $productCount++;
                }
            }
        }

        $this->command->info("✅ {$productCount} products created successfully with multiple images!");
        $this->command->info('📸 Each product has 2-3 images from your collection');
    }
}
