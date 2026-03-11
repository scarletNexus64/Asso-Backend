<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Shop;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create admin user
        $admin = User::create([
            'first_name' => 'Admin',
            'last_name' => 'ASSO',
            'email' => 'admin@asso.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'gender' => 'male',
            'phone' => '+229 97 00 00 00',
            'country' => 'Bénin',
            'email_verified_at' => now(),
        ]);

        // Create sample users for testing
        $livreur = User::create([
            'first_name' => 'Jean',
            'last_name' => 'Livreur',
            'email' => 'livreur@asso.com',
            'password' => Hash::make('password'),
            'role' => 'livreur',
            'gender' => 'male',
            'birth_date' => '1990-05-15',
            'phone' => '+229 97 11 11 11',
            'country' => 'Bénin',
            'email_verified_at' => now(),
        ]);

        $vendeur = User::create([
            'first_name' => 'Marie',
            'last_name' => 'Vendeur',
            'email' => 'vendeur@asso.com',
            'password' => Hash::make('password'),
            'role' => 'vendeur',
            'gender' => 'female',
            'birth_date' => '1992-08-20',
            'phone' => '+229 97 22 22 22',
            'country' => 'Bénin',
            'address' => 'Cotonou, Bénin',
            'email_verified_at' => now(),
        ]);

        $client = User::create([
            'first_name' => 'Paul',
            'last_name' => 'Client',
            'email' => 'client@asso.com',
            'password' => Hash::make('password'),
            'role' => 'client',
            'gender' => 'male',
            'birth_date' => '1995-03-10',
            'phone' => '+229 97 33 33 33',
            'country' => 'Bénin',
            'email_verified_at' => now(),
        ]);

        // Create sample shops for vendeur
        Shop::create([
            'user_id' => $vendeur->id,
            'name' => 'Boutique Marie',
            'slug' => Str::slug('Boutique Marie'),
            'description' => 'Vente de produits alimentaires et cosmétiques',
            'shop_link' => 'https://boutique-marie.asso.com',
            'status' => 'active',
        ]);

        Shop::create([
            'user_id' => $vendeur->id,
            'name' => 'Marie Fashion',
            'slug' => Str::slug('Marie Fashion'),
            'description' => 'Vêtements et accessoires de mode',
            'status' => 'active',
        ]);
    }
}
