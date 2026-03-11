<?php

namespace Database\Seeders;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\ContactClick;
use App\Models\User;
use App\Models\Product;
use Illuminate\Database\Seeder;

class ExchangesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = User::all();
        $products = Product::all();

        if ($users->count() < 2 || $products->count() < 1) {
            $this->command->warn('Pas assez d\'utilisateurs ou de produits. Seeding annulé.');
            return;
        }

        $this->command->info('Génération des conversations et messages...');

        // Générer 50 conversations
        for ($i = 0; $i < 50; $i++) {
            $user1 = $users->random();
            $user2 = $users->where('id', '!=', $user1->id)->random();
            $product = $products->random();

            // Créer la conversation
            $conversation = Conversation::create([
                'user1_id' => $user1->id,
                'user2_id' => $user2->id,
                'product_id' => $product->id,
                'last_message_at' => now()->subDays(rand(0, 60)),
                'created_at' => now()->subDays(rand(0, 90)),
            ]);

            // Générer entre 2 et 20 messages par conversation
            $messageCount = rand(2, 20);
            $currentDate = $conversation->created_at->copy();

            for ($j = 0; $j < $messageCount; $j++) {
                $sender = rand(0, 1) == 0 ? $user1 : $user2;
                $isRead = rand(0, 100) < 80; // 80% de messages lus

                $messages = [
                    'Bonjour, le produit est-il toujours disponible?',
                    'Oui, toujours disponible!',
                    'Quel est le prix final?',
                    'Le prix est négociable',
                    'Puis-je voir plus de photos?',
                    'Je peux vous envoyer d\'autres photos',
                    'Quand puis-je venir récupérer?',
                    'Vous pouvez venir demain',
                    'Parfait, à demain!',
                    'Merci beaucoup',
                    'Le produit est en bon état?',
                    'Oui, presque neuf',
                    'Acceptez-vous les paiements Mobile Money?',
                    'Oui, tous les modes de paiement sont acceptés',
                    'Est-ce que la livraison est incluse?',
                    'La livraison est gratuite dans la ville',
                    'Excellent!',
                    'Je suis intéressé',
                    'Contactez-moi quand vous êtes libre',
                    'D\'accord, je vous appelle',
                ];

                Message::create([
                    'conversation_id' => $conversation->id,
                    'sender_id' => $sender->id,
                    'message' => $messages[array_rand($messages)],
                    'is_read' => $isRead,
                    'read_at' => $isRead ? $currentDate->copy()->addMinutes(rand(5, 120)) : null,
                    'created_at' => $currentDate,
                ]);

                $currentDate->addMinutes(rand(10, 300));
            }

            // Mettre à jour last_message_at
            $conversation->update([
                'last_message_at' => $currentDate,
            ]);

            if (($i + 1) % 10 == 0) {
                $this->command->info(($i + 1) . ' conversations générées...');
            }
        }

        $this->command->info('✅ 50 conversations avec messages générées!');
        $this->command->info('');
        $this->command->info('Génération des clics de contact...');

        // Générer des clics WhatsApp et appels
        $sellers = $users->where('role', 'vendeur');

        if ($sellers->count() == 0) {
            // Si pas de vendeurs, utiliser n'importe quel utilisateur
            $sellers = $users->take(5);
        }

        $totalClicks = 0;

        foreach ($sellers as $seller) {
            // Générer entre 10 et 50 clics par vendeur
            $clickCount = rand(10, 50);

            for ($i = 0; $i < $clickCount; $i++) {
                $user = $users->where('id', '!=', $seller->id)->random();
                $product = $products->random();
                $contactType = rand(0, 1) == 0 ? 'whatsapp' : 'call';

                ContactClick::create([
                    'user_id' => $user->id,
                    'seller_id' => $seller->id,
                    'product_id' => $product->id,
                    'contact_type' => $contactType,
                    'ip_address' => rand(1, 255) . '.' . rand(1, 255) . '.' . rand(1, 255) . '.' . rand(1, 255),
                    'user_agent' => 'Mozilla/5.0 (compatible; ASSO/1.0)',
                    'created_at' => now()->subDays(rand(0, 60)),
                ]);

                $totalClicks++;
            }
        }

        $this->command->info('✅ ' . $totalClicks . ' clics de contact générés!');
        $this->command->info('');
        $this->command->info('Statistiques:');
        $this->command->info('- Conversations: ' . Conversation::count());
        $this->command->info('- Messages: ' . Message::count());
        $this->command->info('- Messages non lus: ' . Message::unread()->count());
        $this->command->info('- Clics WhatsApp: ' . ContactClick::whatsapp()->count());
        $this->command->info('- Appels: ' . ContactClick::call()->count());
    }
}
