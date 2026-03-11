<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\DocumentCategory;

class DocumentCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Contrats',
                'slug' => 'contrats',
                'description' => 'Contrats et accords commerciaux',
                'icon' => 'fa-file-contract',
                'color' => '#3b82f6',
                'order' => 1,
            ],
            [
                'name' => 'Factures',
                'slug' => 'factures',
                'description' => 'Factures et documents comptables',
                'icon' => 'fa-file-invoice',
                'color' => '#10b981',
                'order' => 2,
            ],
            [
                'name' => 'RH',
                'slug' => 'rh',
                'description' => 'Documents ressources humaines',
                'icon' => 'fa-users',
                'color' => '#8b5cf6',
                'order' => 3,
            ],
            [
                'name' => 'Juridique',
                'slug' => 'juridique',
                'description' => 'Documents juridiques et légaux',
                'icon' => 'fa-gavel',
                'color' => '#f59e0b',
                'order' => 4,
            ],
            [
                'name' => 'Marketing',
                'slug' => 'marketing',
                'description' => 'Documents marketing et communication',
                'icon' => 'fa-bullhorn',
                'color' => '#ef4444',
                'order' => 5,
            ],
        ];

        foreach ($categories as $category) {
            DocumentCategory::create($category);
        }
    }
}
