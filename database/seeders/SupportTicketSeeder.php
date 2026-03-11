<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\SupportTicket;
use App\Models\SupportReply;
use App\Models\User;

class SupportTicketSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Récupérer des utilisateurs (clients)
        $users = User::where('role', 'user')->limit(10)->get();

        // Récupérer des admins
        $admins = User::where('role', 'admin')->get();

        if ($users->isEmpty()) {
            $this->command->error('Aucun utilisateur trouvé. Veuillez d\'abord créer des utilisateurs.');
            return;
        }

        if ($admins->isEmpty()) {
            $this->command->error('Aucun admin trouvé. Veuillez d\'abord créer des admins.');
            return;
        }

        $admin = $admins->first();

        // Données de tickets variés
        $tickets = [
            // Tickets techniques
            [
                'subject' => 'Problème de connexion à mon compte',
                'message' => 'Bonjour, je n\'arrive plus à me connecter à mon compte depuis hier. J\'ai essayé de réinitialiser mon mot de passe mais je ne reçois pas l\'email. Pouvez-vous m\'aider ?',
                'category' => 'technique',
                'priority' => 'high',
                'status' => 'resolved',
                'replies' => [
                    ['message' => 'Bonjour, nous avons identifié le problème. Votre compte était temporairement suspendu. Nous l\'avons réactivé. Pouvez-vous réessayer ?', 'is_admin' => true],
                    ['message' => 'Parfait, ça fonctionne maintenant ! Merci beaucoup pour votre aide rapide.', 'is_admin' => false],
                    ['message' => 'Excellent ! N\'hésitez pas à nous contacter si vous avez d\'autres questions.', 'is_admin' => true],
                ]
            ],
            [
                'subject' => 'Site très lent depuis ce matin',
                'message' => 'Le site est extrêmement lent depuis ce matin. Les pages mettent plus de 30 secondes à charger. Est-ce normal ?',
                'category' => 'technique',
                'priority' => 'urgent',
                'status' => 'in_progress',
                'replies' => [
                    ['message' => 'Nous avons détecté une surcharge serveur. Notre équipe technique travaille sur le problème. Nous vous tiendrons informé.', 'is_admin' => true],
                    ['message' => 'D\'accord, merci. Savez-vous quand ce sera résolu ?', 'is_admin' => false],
                ]
            ],

            // Tickets paiement
            [
                'subject' => 'Paiement non validé',
                'message' => 'J\'ai effectué un paiement de 50 000 XOF il y a 2 jours via Orange Money mais ma commande n\'est toujours pas validée. Transaction ID: OM123456789',
                'category' => 'payment',
                'priority' => 'high',
                'status' => 'resolved',
                'replies' => [
                    ['message' => 'Nous avons vérifié votre transaction. Il y avait un délai de synchronisation avec Orange Money. Nous avons validé manuellement votre commande. Vous devriez recevoir votre produit sous 24h.', 'is_admin' => true],
                    ['message' => 'Super ! Merci pour la rapidité. Je vais surveiller la livraison.', 'is_admin' => false],
                ]
            ],
            [
                'subject' => 'Remboursement non reçu',
                'message' => 'Vous m\'avez dit que mon remboursement serait effectué sous 7 jours mais cela fait 10 jours et je n\'ai toujours rien reçu.',
                'category' => 'payment',
                'priority' => 'urgent',
                'status' => 'in_progress',
                'replies' => [
                    ['message' => 'Je m\'excuse pour ce retard. Je vais vérifier immédiatement auprès de notre service comptabilité et vous revenir dans l\'heure.', 'is_admin' => true],
                ]
            ],
            [
                'subject' => 'Problème de facturation',
                'message' => 'Ma facture indique 75 000 XOF mais j\'ai été débité de 85 000 XOF. Pouvez-vous vérifier ?',
                'category' => 'payment',
                'priority' => 'medium',
                'status' => 'open',
                'replies' => []
            ],

            // Tickets produits
            [
                'subject' => 'Produit reçu endommagé',
                'message' => 'J\'ai reçu ma commande (#CMD-12345) mais le produit est endommagé. La boîte était déjà ouverte et le contenu abîmé. Que puis-je faire ?',
                'category' => 'product',
                'priority' => 'high',
                'status' => 'resolved',
                'replies' => [
                    ['message' => 'Je suis vraiment désolé pour ce désagrément. Nous allons vous envoyer un produit de remplacement immédiatement. Pouvez-vous nous envoyer une photo du produit endommagé ?', 'is_admin' => true],
                    ['message' => 'Voici les photos. [Photos envoyées]', 'is_admin' => false],
                    ['message' => 'Merci. Votre nouveau produit sera expédié aujourd\'hui même. Vous recevrez le numéro de suivi par email.', 'is_admin' => true],
                    ['message' => 'Parfait, merci pour votre professionnalisme !', 'is_admin' => false],
                ]
            ],
            [
                'subject' => 'Mauvais produit livré',
                'message' => 'J\'ai commandé un ordinateur portable mais j\'ai reçu une tablette. Commande #CMD-98765',
                'category' => 'product',
                'priority' => 'urgent',
                'status' => 'in_progress',
                'replies' => [
                    ['message' => 'C\'est inacceptable de notre part. Nous organisons immédiatement la récupération de la tablette et l\'envoi de votre ordinateur. Toutes nos excuses.', 'is_admin' => true],
                ]
            ],

            // Tickets compte
            [
                'subject' => 'Modification d\'adresse email',
                'message' => 'Je souhaite changer mon adresse email de contact. Comment faire ?',
                'category' => 'account',
                'priority' => 'low',
                'status' => 'resolved',
                'replies' => [
                    ['message' => 'Vous pouvez modifier votre email dans Mon Compte > Paramètres. Quelle est la nouvelle adresse email que vous souhaitez utiliser ?', 'is_admin' => true],
                    ['message' => 'Je voudrais utiliser nouvel.email@example.com', 'is_admin' => false],
                    ['message' => 'Nous avons mis à jour votre adresse email. Vous allez recevoir un email de confirmation à votre nouvelle adresse.', 'is_admin' => true],
                ]
            ],
            [
                'subject' => 'Suppression de compte',
                'message' => 'Je souhaite supprimer définitivement mon compte et toutes mes données personnelles.',
                'category' => 'account',
                'priority' => 'medium',
                'status' => 'open',
                'replies' => []
            ],

            // Autres tickets
            [
                'subject' => 'Suggestion d\'amélioration',
                'message' => 'Bonjour, j\'utilise votre plateforme régulièrement et j\'aimerais suggérer l\'ajout d\'un mode sombre. Ce serait vraiment appréciable pour l\'utilisation nocturne.',
                'category' => 'other',
                'priority' => 'low',
                'status' => 'closed',
                'replies' => [
                    ['message' => 'Merci beaucoup pour votre suggestion ! Nous prenons note et allons la transmettre à notre équipe de développement.', 'is_admin' => true],
                    ['message' => 'Super ! J\'espère que cette fonctionnalité sera ajoutée bientôt.', 'is_admin' => false],
                    ['message' => 'Nous fermons ce ticket mais gardons votre suggestion en considération. Merci encore !', 'is_admin' => true],
                ]
            ],
            [
                'subject' => 'Partenariat commercial',
                'message' => 'Bonjour, je représente une entreprise qui souhaiterait établir un partenariat. À qui puis-je m\'adresser ?',
                'category' => 'other',
                'priority' => 'medium',
                'status' => 'in_progress',
                'replies' => [
                    ['message' => 'Bonjour, merci pour votre intérêt. Pour les partenariats, veuillez contacter directement notre service commercial à partenariats@example.com', 'is_admin' => true],
                ]
            ],
            [
                'subject' => 'Question sur les délais de livraison',
                'message' => 'Quels sont vos délais de livraison pour la région de Thiès ?',
                'category' => 'other',
                'priority' => 'low',
                'status' => 'open',
                'replies' => []
            ],
        ];

        $this->command->info('Création des tickets de support...');

        foreach ($tickets as $index => $ticketData) {
            // Sélectionner un utilisateur aléatoire
            $user = $users->random();

            // Créer le ticket
            $ticket = SupportTicket::create([
                'user_id' => $user->id,
                'subject' => $ticketData['subject'],
                'message' => $ticketData['message'],
                'category' => $ticketData['category'],
                'priority' => $ticketData['priority'],
                'status' => $ticketData['status'],
                'admin_id' => in_array($ticketData['status'], ['in_progress', 'resolved', 'closed']) ? $admin->id : null,
                'resolved_at' => in_array($ticketData['status'], ['resolved', 'closed']) ? now()->subDays(rand(0, 5)) : null,
                'created_at' => now()->subDays(rand(0, 30)),
            ]);

            // Créer les réponses
            if (!empty($ticketData['replies'])) {
                foreach ($ticketData['replies'] as $replyIndex => $replyData) {
                    SupportReply::create([
                        'support_ticket_id' => $ticket->id,
                        'user_id' => $replyData['is_admin'] ? $admin->id : $user->id,
                        'message' => $replyData['message'],
                        'is_admin' => $replyData['is_admin'],
                        'created_at' => $ticket->created_at->addHours($replyIndex + 1),
                    ]);
                }
            }

            $this->command->info("✓ Ticket créé: {$ticket->subject}");
        }

        $this->command->info("\n✓ " . count($tickets) . " tickets de support créés avec succès !");
    }
}
