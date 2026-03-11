<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;

class UpdateUserRolesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Mise à jour des rôles des utilisateurs...');

        // Récupérer tous les utilisateurs non-admin
        $users = User::where('role', '!=', 'admin')->get();

        $roles = ['client', 'vendeur', 'livreur'];
        $updated = 0;

        foreach ($users as $user) {
            // Assigner un rôle aléatoire
            $user->role = $roles[array_rand($roles)];
            $user->save();
            $updated++;
        }

        $this->command->info("✓ {$updated} utilisateurs mis à jour avec des rôles aléatoires!");

        // Afficher les statistiques
        $clientsCount = User::where('role', 'client')->count();
        $vendeursCount = User::where('role', 'vendeur')->count();
        $livreursCount = User::where('role', 'livreur')->count();

        $this->command->info("\nStatistiques:");
        $this->command->info("- Clients: {$clientsCount}");
        $this->command->info("- Vendeurs: {$vendeursCount}");
        $this->command->info("- Livreurs: {$livreursCount}");
    }
}
