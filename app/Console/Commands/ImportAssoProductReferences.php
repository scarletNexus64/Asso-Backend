<?php

namespace App\Console\Commands;

use App\Models\AssoProductReference;
use App\Models\Product;
use App\Support\AssoScope;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/** Extraction conservative : les textes d'origine et leurs traductions ne sont jamais modifiés. */
class ImportAssoProductReferences extends Command
{
    protected $signature = 'asso:import-product-references {--apply : Enregistrer les références et leurs associations}';
    protected $description = 'Prévisualise ou reprend les codes de référence présents dans les informations commerciales ASSO';

    public function handle(): int
    {
        $owner = AssoScope::ownerId();
        if ($owner <= 0) {
            $this->error('Compte plateforme introuvable.');
            return self::FAILURE;
        }
        $stats = ['codes' => [], 'ambiguous' => [], 'already_linked' => 0];
        $apply = $this->option('apply');
        $run = function () use ($owner, $apply, &$stats) {
            $products = Product::whereHas('shop', fn ($q) => $q->where('user_id', $owner))
                ->with(['translations' => fn ($q) => $q->where('field', 'commercial_information')])
                ->orderBy('id')->get();
            foreach ($products as $product) {
                $texts = $product->translations->pluck('value')->push($product->getRawOriginal('commercial_information'));
                $codes = [];
                foreach ($texts as $text) {
                    preg_match_all('/\b(?:ASSO-\s*)?FRN-[A-Z]+(?:-[A-Z]+)?-[0-9]+\b/iu', (string) $text, $matches);
                    foreach ($matches[0] as $code) {
                        $code = strtoupper(preg_replace('/\s+/u', '', $code));
                        $codes[$code] = true;
                    }
                }
                if (count($codes) > 1) {
                    $stats['ambiguous'][$product->id] = array_keys($codes);
                    continue;
                }
                if (! $codes) continue;
                $code = array_key_first($codes);
                $stats['codes'][$code] = ($stats['codes'][$code] ?? 0) + 1;
                if ($product->asso_product_reference_id) {
                    $stats['already_linked']++;
                    continue;
                }
                if ($apply) {
                    $reference = AssoProductReference::firstOrCreate(['name' => $code], ['is_active' => true]);
                    // Ne jamais écraser un rattachement effectué depuis l'inventaire.
                    Product::whereKey($product->id)->whereNull('asso_product_reference_id')
                        ->update(['asso_product_reference_id' => $reference->id]);
                }
            }
        };
        $apply ? DB::transaction($run) : $run();
        ksort($stats['codes']);
        $this->line(json_encode(['mode' => $apply ? 'apply' : 'preview'] + $stats, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        return self::SUCCESS;
    }
}
