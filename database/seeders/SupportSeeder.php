<?php

namespace Database\Seeders;

use App\Models\SupportTicket;
use App\Models\SupportReply;
use App\Models\User;
use Illuminate\Database\Seeder;

class SupportSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Génération des tickets de support...');

        $users = User::where('role', 'user')->get();
        $admins = User::where('role', 'admin')->get();

        if ($users->isEmpty() || $admins->isEmpty()) {
            $this->command->warn('⚠️  Aucun utilisateur ou admin trouvé. Création annulée.');
            return;
        }

        $admin = $admins->first();

        // ========================================
        // 1. Tickets Techniques
        // ========================================
        $this->command->info('');
        $this->command->info('🔧 Tickets Techniques:');

        $technicalTickets = [
            [
                'subject' => 'Impossible de télécharger des images',
                'message' => 'Bonjour, j\'essaie de télécharger des images pour mes produits mais j\'obtiens toujours une erreur "Upload failed". Pouvez-vous m\'aider ?',
                'status' => 'in_progress',
                'priority' => 'high',
                'category' => 'technique',
                'admin_id' => $admin->id,
            ],
            [
                'subject' => 'La recherche ne fonctionne pas correctement',
                'message' => 'Quand je recherche "smartphone", aucun résultat n\'apparaît alors que j\'ai plusieurs produits avec ce mot clé.',
                'status' => 'resolved',
                'priority' => 'medium',
                'category' => 'technique',
                'admin_id' => $admin->id,
                'resolved_at' => now()->subDays(2),
            ],
            [
                'subject' => 'Erreur 500 lors de la modification du profil',
                'message' => 'À chaque fois que j\'essaie de modifier mon profil, je reçois une erreur 500. C\'est très frustrant.',
                'status' => 'open',
                'priority' => 'urgent',
                'category' => 'technique',
            ],
        ];

        foreach ($technicalTickets as $ticketData) {
            $ticket = SupportTicket::create([
                'user_id' => $users->random()->id,
                'subject' => $ticketData['subject'],
                'message' => $ticketData['message'],
                'status' => $ticketData['status'],
                'priority' => $ticketData['priority'],
                'category' => $ticketData['category'],
                'admin_id' => $ticketData['admin_id'] ?? null,
                'resolved_at' => $ticketData['resolved_at'] ?? null,
            ]);

            // Ajouter des réponses pour les tickets en cours ou résolus
            if (in_array($ticket->status, ['in_progress', 'resolved'])) {
                SupportReply::create([
                    'support_ticket_id' => $ticket->id,
                    'user_id' => $admin->id,
                    'message' => 'Bonjour, merci pour votre message. Nous avons bien reçu votre demande et notre équipe technique est en train d\'analyser le problème.',
                    'is_admin' => true,
                ]);

                if ($ticket->status === 'resolved') {
                    SupportReply::create([
                        'support_ticket_id' => $ticket->id,
                        'user_id' => $ticket->user_id,
                        'message' => 'D\'accord, merci pour votre retour rapide.',
                        'is_admin' => false,
                    ]);

                    SupportReply::create([
                        'support_ticket_id' => $ticket->id,
                        'user_id' => $admin->id,
                        'message' => 'Le problème a été identifié et corrigé. La recherche fonctionne maintenant correctement. Pouvez-vous réessayer et nous confirmer ?',
                        'is_admin' => true,
                    ]);

                    SupportReply::create([
                        'support_ticket_id' => $ticket->id,
                        'user_id' => $ticket->user_id,
                        'message' => 'Super ! Ça fonctionne parfaitement maintenant. Merci beaucoup !',
                        'is_admin' => false,
                    ]);
                }
            }

            $this->command->info('  ✓ ' . $ticket->ticket_number . ' - ' . $ticket->subject . ' [' . $ticket->status_label . ']');
        }

        // ========================================
        // 2. Tickets Paiement
        // ========================================
        $this->command->info('');
        $this->command->info('💳 Tickets Paiement:');

        $paymentTickets = [
            [
                'subject' => 'Paiement non enregistré',
                'message' => 'J\'ai effectué un paiement de 5000 XOF via FedaPay mais mon package n\'est toujours pas activé. Transaction ID: FDP-123456789',
                'status' => 'in_progress',
                'priority' => 'urgent',
                'category' => 'payment',
                'admin_id' => $admin->id,
            ],
            [
                'subject' => 'Demande de remboursement',
                'message' => 'J\'ai acheté le mauvais package par erreur. Puis-je obtenir un remboursement ?',
                'status' => 'open',
                'priority' => 'medium',
                'category' => 'payment',
            ],
            [
                'subject' => 'Problème avec PayPal',
                'message' => 'Le paiement PayPal ne fonctionne pas. Le bouton ne réagit pas quand je clique dessus.',
                'status' => 'resolved',
                'priority' => 'high',
                'category' => 'payment',
                'admin_id' => $admin->id,
                'resolved_at' => now()->subDays(1),
            ],
        ];

        foreach ($paymentTickets as $ticketData) {
            $ticket = SupportTicket::create([
                'user_id' => $users->random()->id,
                'subject' => $ticketData['subject'],
                'message' => $ticketData['message'],
                'status' => $ticketData['status'],
                'priority' => $ticketData['priority'],
                'category' => $ticketData['category'],
                'admin_id' => $ticketData['admin_id'] ?? null,
                'resolved_at' => $ticketData['resolved_at'] ?? null,
            ]);

            if ($ticket->status === 'in_progress') {
                SupportReply::create([
                    'support_ticket_id' => $ticket->id,
                    'user_id' => $admin->id,
                    'message' => 'Bonjour, nous vérifions votre transaction avec FedaPay. Pouvez-vous nous envoyer une capture d\'écran de la confirmation de paiement ?',
                    'is_admin' => true,
                ]);
            }

            $this->command->info('  ✓ ' . $ticket->ticket_number . ' - ' . $ticket->subject . ' [' . $ticket->status_label . ']');
        }

        // ========================================
        // 3. Tickets Produits
        // ========================================
        $this->command->info('');
        $this->command->info('📦 Tickets Produits:');

        $productTickets = [
            [
                'subject' => 'Impossible de supprimer un produit',
                'message' => 'Le bouton de suppression ne fonctionne pas sur mes produits.',
                'status' => 'closed',
                'priority' => 'low',
                'category' => 'product',
                'admin_id' => $admin->id,
                'resolved_at' => now()->subDays(5),
            ],
            [
                'subject' => 'Produit non visible sur la plateforme',
                'message' => 'J\'ai publié un produit il y a 2 jours mais il n\'apparaît toujours pas dans les recherches.',
                'status' => 'in_progress',
                'priority' => 'high',
                'category' => 'product',
                'admin_id' => $admin->id,
            ],
        ];

        foreach ($productTickets as $ticketData) {
            $ticket = SupportTicket::create([
                'user_id' => $users->random()->id,
                'subject' => $ticketData['subject'],
                'message' => $ticketData['message'],
                'status' => $ticketData['status'],
                'priority' => $ticketData['priority'],
                'category' => $ticketData['category'],
                'admin_id' => $ticketData['admin_id'] ?? null,
                'resolved_at' => $ticketData['resolved_at'] ?? null,
            ]);

            if ($ticket->status === 'in_progress') {
                SupportReply::create([
                    'support_ticket_id' => $ticket->id,
                    'user_id' => $admin->id,
                    'message' => 'Nous vérifions pourquoi votre produit n\'apparaît pas. Quel est le nom du produit concerné ?',
                    'is_admin' => true,
                ]);
            }

            $this->command->info('  ✓ ' . $ticket->ticket_number . ' - ' . $ticket->subject . ' [' . $ticket->status_label . ']');
        }

        // ========================================
        // 4. Tickets Compte
        // ========================================
        $this->command->info('');
        $this->command->info('👤 Tickets Compte:');

        $accountTickets = [
            [
                'subject' => 'Impossible de me connecter',
                'message' => 'J\'ai oublié mon mot de passe et le lien de réinitialisation n\'arrive pas dans ma boîte mail.',
                'status' => 'resolved',
                'priority' => 'urgent',
                'category' => 'account',
                'admin_id' => $admin->id,
                'resolved_at' => now()->subHours(12),
            ],
            [
                'subject' => 'Demande de suppression de compte',
                'message' => 'Je souhaite supprimer définitivement mon compte et toutes mes données.',
                'status' => 'open',
                'priority' => 'medium',
                'category' => 'account',
            ],
        ];

        foreach ($accountTickets as $ticketData) {
            $ticket = SupportTicket::create([
                'user_id' => $users->random()->id,
                'subject' => $ticketData['subject'],
                'message' => $ticketData['message'],
                'status' => $ticketData['status'],
                'priority' => $ticketData['priority'],
                'category' => $ticketData['category'],
                'admin_id' => $ticketData['admin_id'] ?? null,
                'resolved_at' => $ticketData['resolved_at'] ?? null,
            ]);

            $this->command->info('  ✓ ' . $ticket->ticket_number . ' - ' . $ticket->subject . ' [' . $ticket->status_label . ']');
        }

        // ========================================
        // Statistiques finales
        // ========================================
        $this->command->info('');
        $this->command->info('========================================');
        $this->command->info('✅ Tickets de support générés avec succès!');
        $this->command->info('');
        $this->command->info('Statistiques:');
        $this->command->info('- Total: ' . SupportTicket::count() . ' tickets');
        $this->command->info('- Ouverts: ' . SupportTicket::open()->count() . ' tickets');
        $this->command->info('- En cours: ' . SupportTicket::inProgress()->count() . ' tickets');
        $this->command->info('- Résolus: ' . SupportTicket::resolved()->count() . ' tickets');
        $this->command->info('- Fermés: ' . SupportTicket::closed()->count() . ' tickets');
        $this->command->info('- Réponses: ' . SupportReply::count() . ' réponses');
        $this->command->info('========================================');
    }
}
