<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Support\AssoScope;
use Illuminate\Console\Command;

/** Inventaire en lecture seule : ne tente pas de deviner les références. */
class InventoryAssoProductReferences extends Command
{
    protected $signature = 'asso:inventory-product-references';

    protected $description = 'Inventorie les informations commerciales des produits ASSO au format JSON, sans modification';

    public function handle(): int
    {
        $ownerId = AssoScope::ownerId();
        if ($ownerId <= 0) {
            $this->error('Compte plateforme ASSO introuvable : inventaire interrompu.');

            return self::FAILURE;
        }

        $products = Product::query()
            ->whereHas('shop', fn ($query) => $query->where('user_id', $ownerId))
            ->with(['shop:id,name,user_id', 'translations' => fn ($query) => $query->where('field', 'commercial_information')])
            ->orderBy('id')
            ->get();

        $rows = $products->map(function (Product $product) {
            $source = $product->getRawOriginal('commercial_information');
            $translations = $product->translations->mapWithKeys(fn ($translation) => [$translation->locale => $translation->value])->all();

            return [
                'product_id' => $product->id,
                'product_name' => $product->getRawOriginal('name'),
                'shop_id' => $product->shop_id,
                'shop_name' => $product->shop?->name,
                'commercial_information' => $source,
                'translations' => $translations,
            ];
        })->filter(fn ($row) => filled($row['commercial_information']) || $row['translations'] !== [])->values();

        $this->line(json_encode([
            'generated_at' => now()->toIso8601String(),
            'product_count' => $products->count(),
            'products_with_information_count' => $rows->count(),
            'products' => $rows,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

        return self::SUCCESS;
    }
}
