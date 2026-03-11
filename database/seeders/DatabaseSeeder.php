<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->command->info('🌱 Starting database seeding...');

        // Seed in correct order due to foreign key constraints
        $this->call([
            UserSeeder::class,
            ShopSeeder::class,
            CategorySeeder::class,
            ProductSeeder::class,
            SettingsSeeder::class,
            LegalPagesSeeder::class,
        ]);

        $this->command->info('');
        $this->command->info('✅ Database seeding completed successfully!');
        $this->command->info('📊 Your database is now populated with:');
        $this->command->info('   - Users (admins, sellers, customers, delivery persons)');
        $this->command->info('   - Shops with detailed descriptions');
        $this->command->info('   - Categories and subcategories');
        $this->command->info('   - Hundreds of products with multiple images and rich descriptions');
        $this->command->info('   - Settings and configurations (42 parameters)');
        $this->command->info('   - Legal pages (CGU, Privacy Policy, Terms)');
        $this->command->info('');
        $this->command->info('🔐 Login credentials:');
        $this->command->info('   Admin: admin@asso.com / password');
        $this->command->info('   Seller: amina.kossou@vendeur.com / password');
        $this->command->info('   Customer: marie.ahossou@client.com / password');
    }
}
