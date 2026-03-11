<?php

namespace Database\Seeders;

use App\Models\Transaction;
use App\Models\User;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class TransactionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Récupérer des utilisateurs et produits pour les relations
        $users = User::all();
        $products = Product::all();

        if ($users->count() < 2 || $products->count() < 1) {
            $this->command->warn('Pas assez d\'utilisateurs ou de produits. Seeding annulé.');
            return;
        }

        $paymentMethods = ['paypal', 'visa', 'mastercard', 'fedapay'];
        $statuses = ['completed', 'pending', 'cancelled'];
        $types = ['purchase', 'refund', 'exchange'];

        // Générer 200 transactions sur les 6 derniers mois
        $this->command->info('Génération de 200 transactions...');

        for ($i = 0; $i < 200; $i++) {
            // Date aléatoire dans les 6 derniers mois
            $createdAt = now()->subDays(rand(0, 180));

            // Méthode de paiement aléatoire
            $paymentMethod = $paymentMethods[array_rand($paymentMethods)];

            // Montant aléatoire entre 5,000 et 500,000 CFA
            $amount = rand(5000, 500000);

            // Frais selon la méthode de paiement
            $fees = match($paymentMethod) {
                'paypal' => $amount * 0.029 + 200, // 2.9% + 200 CFA
                'visa', 'mastercard' => $amount * 0.025, // 2.5%
                'fedapay' => $amount * 0.015, // 1.5%
                default => 0,
            };

            $netAmount = $amount - $fees;

            // Statut (80% completed, 15% pending, 5% cancelled)
            $rand = rand(1, 100);
            if ($rand <= 80) {
                $status = 'completed';
            } elseif ($rand <= 95) {
                $status = 'pending';
            } else {
                $status = 'cancelled';
            }

            // Type (90% purchase, 7% exchange, 3% refund)
            $rand = rand(1, 100);
            if ($rand <= 90) {
                $type = 'purchase';
            } elseif ($rand <= 97) {
                $type = 'exchange';
            } else {
                $type = 'refund';
            }

            $buyer = $users->random();
            $seller = $users->where('id', '!=', $buyer->id)->random();
            $product = $products->random();

            Transaction::create([
                'reference' => 'TXN-' . strtoupper(Str::random(10)),
                'transaction_id' => strtoupper(Str::random(16)),
                'buyer_id' => $buyer->id,
                'seller_id' => $seller->id,
                'product_id' => $product->id,
                'amount' => $amount,
                'fees' => round($fees, 2),
                'net_amount' => round($netAmount, 2),
                'currency' => 'XOF',
                'status' => $status,
                'type' => $type,
                'payment_method' => $paymentMethod,
                'external_reference' => match($paymentMethod) {
                    'paypal' => 'PP-' . strtoupper(Str::random(12)),
                    'visa' => 'VISA-' . strtoupper(Str::random(12)),
                    'mastercard' => 'MC-' . strtoupper(Str::random(12)),
                    'fedapay' => 'FEDA-' . strtoupper(Str::random(12)),
                    default => null,
                },
                'description' => 'Paiement pour ' . $product->name,
                'metadata' => [
                    'ip_address' => rand(1, 255) . '.' . rand(1, 255) . '.' . rand(1, 255) . '.' . rand(1, 255),
                    'user_agent' => 'Mozilla/5.0 (compatible; ASSO/1.0)',
                    'device' => ['mobile', 'desktop', 'tablet'][rand(0, 2)],
                ],
                'payer_email' => $buyer->email,
                'payer_name' => $buyer->first_name . ' ' . $buyer->last_name,
                'completed_at' => $status === 'completed' ? $createdAt->addMinutes(rand(1, 30)) : null,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);

            if (($i + 1) % 50 == 0) {
                $this->command->info(($i + 1) . ' transactions générées...');
            }
        }

        $this->command->info('✅ 200 transactions générées avec succès !');

        // Statistiques
        $this->command->info('');
        $this->command->info('Statistiques:');
        $this->command->info('- PayPal: ' . Transaction::where('payment_method', 'paypal')->count());
        $this->command->info('- Visa: ' . Transaction::where('payment_method', 'visa')->count());
        $this->command->info('- MasterCard: ' . Transaction::where('payment_method', 'mastercard')->count());
        $this->command->info('- FedaPay: ' . Transaction::where('payment_method', 'fedapay')->count());
        $this->command->info('- Total: ' . number_format(Transaction::sum('amount'), 0, ',', ' ') . ' CFA');
        $this->command->info('- Frais totaux: ' . number_format(Transaction::sum('fees'), 0, ',', ' ') . ' CFA');
        $this->command->info('- Montant net: ' . number_format(Transaction::sum('net_amount'), 0, ',', ' ') . ' CFA');
    }
}
