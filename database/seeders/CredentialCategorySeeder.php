<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\CredentialCategory;

class CredentialCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Google / Gmail',
                'slug' => 'google',
                'icon' => 'fa-brands fa-google',
                'color' => '#ea4335',
                'description' => 'Comptes Google, Gmail, GSuite',
                'order' => 1,
            ],
            [
                'name' => 'AWS / Cloud',
                'slug' => 'aws-cloud',
                'icon' => 'fa-brands fa-aws',
                'color' => '#ff9900',
                'description' => 'AWS, Azure, GCP, DigitalOcean',
                'order' => 2,
            ],
            [
                'name' => 'API Keys',
                'slug' => 'api-keys',
                'icon' => 'fa-code',
                'color' => '#10b981',
                'description' => 'Clés API, Tokens, Webhooks',
                'order' => 3,
            ],
            [
                'name' => 'Bases de données',
                'slug' => 'databases',
                'icon' => 'fa-database',
                'color' => '#3b82f6',
                'description' => 'MySQL, PostgreSQL, MongoDB, Redis',
                'order' => 4,
            ],
            [
                'name' => 'SSH / Serveurs',
                'slug' => 'ssh-servers',
                'icon' => 'fa-server',
                'color' => '#8b5cf6',
                'description' => 'Clés SSH, Accès serveurs, VPS',
                'order' => 5,
            ],
            [
                'name' => 'Réseaux Sociaux',
                'slug' => 'social-media',
                'icon' => 'fa-share-nodes',
                'color' => '#ec4899',
                'description' => 'Facebook, Twitter, LinkedIn, Instagram',
                'order' => 6,
            ],
            [
                'name' => 'Paiements',
                'slug' => 'payments',
                'icon' => 'fa-credit-card',
                'color' => '#f59e0b',
                'description' => 'Stripe, PayPal, Fedapay',
                'order' => 7,
            ],
            [
                'name' => 'Autres',
                'slug' => 'others',
                'icon' => 'fa-key',
                'color' => '#6b7280',
                'description' => 'Autres credentials',
                'order' => 8,
            ],
        ];

        foreach ($categories as $category) {
            CredentialCategory::create($category);
        }
    }
}
