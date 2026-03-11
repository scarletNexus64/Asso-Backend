<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\AffiliateCommission;
use App\Models\AffiliateSetting;
use App\Models\Transaction;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AffiliateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Génération du réseau d\'affiliation...');

        $settings = AffiliateSetting::getSettings();

        // ========================================
        // 1. Créer des parrains principaux (niveau 0)
        // ========================================
        $this->command->info('');
        $this->command->info('👥 Création des parrains principaux:');

        $mainReferrers = [];
        $referrerNames = [
            ['Jean', 'Dupont'],
            ['Marie', 'Martin'],
            ['Pierre', 'Durand'],
            ['Sophie', 'Leroy'],
            ['Thomas', 'Moreau'],
        ];

        foreach ($referrerNames as [$firstName, $lastName]) {
            $user = User::create([
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => strtolower($firstName) . '.' . strtolower($lastName) . '@example.com',
                'password' => Hash::make('password'),
                'role' => 'user',
                'phone' => '0' . rand(600000000, 699999999),
                'country' => 'Cameroun',
            ]);

            $mainReferrers[] = $user;
            $this->command->info('  ✓ ' . $user->name . ' - Code: ' . $user->referral_code);
        }

        // ========================================
        // 2. Créer des filleuls niveau 1
        // ========================================
        $this->command->info('');
        $this->command->info('👤 Création des filleuls niveau 1:');

        $level1Referrals = [];
        $firstNames = ['Alice', 'Bob', 'Claire', 'David', 'Emma', 'François', 'Gabrielle', 'Henri'];
        $lastNames = ['Bernard', 'Petit', 'Robert', 'Richard', 'Dubois', 'Roux', 'Vincent', 'Fournier'];

        foreach ($mainReferrers as $referrer) {
            $numReferrals = rand(2, 4);
            for ($i = 0; $i < $numReferrals; $i++) {
                $firstName = $firstNames[array_rand($firstNames)];
                $lastName = $lastNames[array_rand($lastNames)];

                $user = User::create([
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'email' => strtolower($firstName) . '.' . strtolower($lastName) . rand(1, 999) . '@example.com',
                    'password' => Hash::make('password'),
                    'role' => 'user',
                    'phone' => '0' . rand(600000000, 699999999),
                    'country' => 'Cameroun',
                    'referred_by_id' => $referrer->id,
                ]);

                $level1Referrals[] = $user;
            }
        }

        $this->command->info('  ✓ ' . count($level1Referrals) . ' filleuls niveau 1 créés');

        // ========================================
        // 3. Créer des filleuls niveau 2 (si activé)
        // ========================================
        if ($settings->max_levels >= 2) {
            $this->command->info('');
            $this->command->info('👥 Création des filleuls niveau 2:');

            $level2Referrals = [];
            foreach ($level1Referrals as $referrer) {
                if (rand(0, 100) < 60) { // 60% de chance d'avoir des filleuls
                    $numReferrals = rand(1, 2);
                    for ($i = 0; $i < $numReferrals; $i++) {
                        $firstName = $firstNames[array_rand($firstNames)];
                        $lastName = $lastNames[array_rand($lastNames)];

                        $user = User::create([
                            'first_name' => $firstName,
                            'last_name' => $lastName,
                            'email' => strtolower($firstName) . '.' . strtolower($lastName) . rand(1000, 9999) . '@example.com',
                            'password' => Hash::make('password'),
                            'role' => 'user',
                            'phone' => '0' . rand(600000000, 699999999),
                            'country' => 'Cameroun',
                            'referred_by_id' => $referrer->id,
                        ]);

                        $level2Referrals[] = $user;
                    }
                }
            }

            $this->command->info('  ✓ ' . count($level2Referrals) . ' filleuls niveau 2 créés');
        }

        // ========================================
        // 4. Créer des commissions simulées
        // ========================================
        $this->command->info('');
        $this->command->info('💰 Génération des commissions:');

        $transactionAmounts = [5000, 10000, 15000, 20000, 25000, 30000, 50000];
        $statuses = ['pending', 'approved', 'paid'];

        $totalCommissions = 0;

        // Commissions pour niveau 1
        foreach ($level1Referrals as $referredUser) {
            $referrer = $referredUser->referrer;
            if (!$referrer) continue;

            $amount = $transactionAmounts[array_rand($transactionAmounts)];
            $percentage = $settings->level_1_percentage;
            $commissionAmount = $amount * ($percentage / 100);

            $commission = AffiliateCommission::create([
                'affiliate_id' => $referrer->id,
                'referred_user_id' => $referredUser->id,
                'level' => 1,
                'amount' => $commissionAmount,
                'percentage' => $percentage,
                'status' => $statuses[array_rand($statuses)],
                'description' => 'Commission niveau 1 sur transaction de ' . number_format($amount) . ' XOF',
            ]);

            if ($commission->status === 'paid') {
                $commission->update(['paid_at' => now()->subDays(rand(1, 30))]);
                $referrer->increment('total_earnings', $commissionAmount);
                $referrer->increment('withdrawn_earnings', $commissionAmount);
            } elseif ($commission->status === 'approved') {
                $referrer->increment('total_earnings', $commissionAmount);
                $referrer->increment('pending_earnings', $commissionAmount);
            } elseif ($commission->status === 'pending') {
                $referrer->increment('pending_earnings', $commissionAmount);
            }

            $totalCommissions++;
        }

        // Commissions pour niveau 2
        if ($settings->max_levels >= 2 && isset($level2Referrals)) {
            foreach ($level2Referrals as $referredUser) {
                $level1Referrer = $referredUser->referrer;
                if (!$level1Referrer) continue;

                $level0Referrer = $level1Referrer->referrer;
                if (!$level0Referrer) continue;

                $amount = $transactionAmounts[array_rand($transactionAmounts)];

                // Commission niveau 1 (parrain direct)
                $percentage1 = $settings->level_1_percentage;
                $commission1Amount = $amount * ($percentage1 / 100);

                AffiliateCommission::create([
                    'affiliate_id' => $level1Referrer->id,
                    'referred_user_id' => $referredUser->id,
                    'level' => 1,
                    'amount' => $commission1Amount,
                    'percentage' => $percentage1,
                    'status' => $statuses[array_rand($statuses)],
                    'description' => 'Commission niveau 1 sur transaction de ' . number_format($amount) . ' XOF',
                ]);

                // Commission niveau 2 (grand-parrain)
                $percentage2 = $settings->level_2_percentage;
                $commission2Amount = $amount * ($percentage2 / 100);

                AffiliateCommission::create([
                    'affiliate_id' => $level0Referrer->id,
                    'referred_user_id' => $referredUser->id,
                    'level' => 2,
                    'amount' => $commission2Amount,
                    'percentage' => $percentage2,
                    'status' => $statuses[array_rand($statuses)],
                    'description' => 'Commission niveau 2 sur transaction de ' . number_format($amount) . ' XOF',
                ]);

                $totalCommissions += 2;
            }
        }

        $this->command->info('  ✓ ' . $totalCommissions . ' commissions générées');

        // ========================================
        // Statistiques finales
        // ========================================
        $this->command->info('');
        $this->command->info('========================================');
        $this->command->info('✅ Réseau d\'affiliation créé avec succès!');
        $this->command->info('');
        $this->command->info('Statistiques:');
        $this->command->info('- Parrains principaux: ' . count($mainReferrers));
        $this->command->info('- Filleuls niveau 1: ' . count($level1Referrals));
        if (isset($level2Referrals)) {
            $this->command->info('- Filleuls niveau 2: ' . count($level2Referrals));
        }
        $this->command->info('- Total utilisateurs affiliés: ' . User::whereNotNull('referred_by_id')->count());
        $this->command->info('- Total commissions: ' . AffiliateCommission::count());
        $this->command->info('- Commissions en attente: ' . AffiliateCommission::pending()->count());
        $this->command->info('- Commissions payées: ' . AffiliateCommission::paid()->count());
        $this->command->info('- Montant total: ' . number_format(AffiliateCommission::sum('amount'), 0, ',', ' ') . ' XOF');
        $this->command->info('========================================');
    }
}
