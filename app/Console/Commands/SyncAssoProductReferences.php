<?php

namespace App\Console\Commands;

use App\Models\AssoProductReference;
use App\Models\Product;
use App\Models\Translation;
use App\Support\AssoReferenceCode;
use App\Support\AssoScope;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Reprend les codes de référence écrits dans les informations commerciales des
 * produits ASSO : crée la référence manquante, renseigne la colonne « Référence ASSO »
 * quand elle est vide, puis retire le code du texte affiché aux clients.
 *
 * Sans --apply, rien n'est écrit en base (simulation + rapport).
 * Avec --apply, une sauvegarde est écrite AVANT toute modification ;
 * --restore=<sauvegarde> --apply remet les produits dans leur état antérieur.
 */
class SyncAssoProductReferences extends Command
{
    protected $signature = 'asso:sync-product-references
        {--apply : Enregistrer les modifications (sinon simulation)}
        {--restore= : Chemin d\'une sauvegarde à restaurer}';

    protected $description = 'Reprend les références ASSO depuis les informations commerciales et les retire du texte';

    private const DIR = 'app/asso-references';

    public function handle(): int
    {
        if ($this->option('restore')) {
            return $this->restore((string) $this->option('restore'));
        }

        $owner = AssoScope::ownerId();
        if ($owner <= 0) {
            $this->error('Compte plateforme ASSO introuvable : opération interrompue.');

            return self::FAILURE;
        }
        $apply = (bool) $this->option('apply');

        $products = Product::query()
            ->whereHas('shop', fn ($query) => $query->where('user_id', $owner))
            ->with(['translations' => fn ($query) => $query->where('field', 'commercial_information')])
            ->orderBy('id')
            ->get();

        $references = AssoProductReference::all()->keyBy(fn ($reference) => AssoReferenceCode::normalize($reference->name));
        $plan = [];
        $report = ['to_create' => [], 'linked' => [], 'already_linked' => [], 'conflicts' => [], 'ambiguous' => [], 'texts_cleaned' => 0, 'texts_emptied' => 0];

        foreach ($products as $product) {
            $source = $product->getRawOriginal('commercial_information');
            $translations = $product->translations;
            $codes = AssoReferenceCode::extract(implode("\n", [$source, ...$translations->pluck('value')->all()]));
            if ($codes === []) {
                continue;
            }
            if (count($codes) > 1) {
                $report['ambiguous'][] = ['product_id' => $product->id, 'codes' => $codes];
                continue;
            }
            $code = $codes[0];
            $reference = $references->get($code);
            $current = $product->asso_product_reference_id;
            if ($current && (int) $current !== (int) $reference?->id) {
                // Rattachement manuel différent du texte : on ne touche à rien.
                $report['conflicts'][] = ['product_id' => $product->id, 'text_code' => $code, 'current_reference_id' => $current];
                continue;
            }
            if (! $reference && ! in_array($code, $report['to_create'], true)) {
                $report['to_create'][] = $code;
            }
            $current ? $report['already_linked'][] = $product->id : $report['linked'][] = $product->id;

            $cleaned = AssoReferenceCode::strip($source);
            $translationChanges = $translations->mapWithKeys(fn ($t) => [$t->id => AssoReferenceCode::strip($t->value)])
                ->filter(fn ($value, $id) => $value !== $translations->firstWhere('id', $id)->value);
            $report['texts_cleaned']++;
            if ($cleaned === '') {
                $report['texts_emptied']++;
            }
            $plan[] = [
                'product_id' => $product->id,
                'code' => $code,
                'link' => ! $current,
                'before' => ['asso_product_reference_id' => $current, 'commercial_information' => $source,
                    'translations' => $translations->map(fn ($t) => ['locale' => $t->locale, 'value' => $t->value])->values()->all()],
                'after' => ['commercial_information' => $source === null ? null : ($cleaned === '' ? null : $cleaned),
                    'translations' => $translationChanges->all()],
            ];
        }

        $stamp = now()->format('Ymd-His');
        $reportPath = $this->write("rapport-{$stamp}.json", ['mode' => $apply ? 'apply' : 'simulation', 'report' => $report, 'changes' => $plan]);

        if ($apply && $plan !== []) {
            // Sauvegarde écrite avant toute écriture en base : sans elle, on n'applique pas.
            $backupPath = $this->write("sauvegarde-{$stamp}.json", ['created_at' => now()->toIso8601String(), 'products' => $plan, 'created_references' => []]);
            $created = DB::transaction(function () use ($plan, &$references) {
                $created = [];
                foreach ($plan as $change) {
                    $reference = $references->get($change['code']);
                    if (! $reference) {
                        $reference = AssoProductReference::create(['name' => $change['code'], 'is_active' => true]);
                        $references->put($change['code'], $reference);
                        $created[] = $reference->id;
                    }
                    $query = DB::table('products')->where('id', $change['product_id']);
                    // Garde-fou : l'état doit être celui lu au moment de la simulation.
                    $change['link']
                        ? $query->whereNull('asso_product_reference_id')
                        : $query->where('asso_product_reference_id', $reference->id);
                    $updated = $query->update([
                        'asso_product_reference_id' => $reference->id,
                        'commercial_information' => $change['after']['commercial_information'],
                    ]);
                    if ($updated !== 1) {
                        throw new \RuntimeException("Produit #{$change['product_id']} modifié entre-temps : rien n'a été enregistré.");
                    }
                    foreach ($change['after']['translations'] as $id => $value) {
                        $value === '' ? Translation::whereKey($id)->delete() : Translation::whereKey($id)->update(['value' => $value]);
                    }
                }

                return $created;
            });
            // Les références créées sont ajoutées à la sauvegarde pour pouvoir les retirer.
            $backup = json_decode(File::get($backupPath), true);
            $backup['created_references'] = $created;
            File::put($backupPath, json_encode($backup, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
            $this->info("Sauvegarde : {$backupPath}");
        }

        $this->table(['', 'Nombre'], [
            ['Références à créer', count($report['to_create']).($report['to_create'] ? ' ('.implode(', ', $report['to_create']).')' : '')],
            ['Produits à rattacher', count($report['linked'])],
            ['Produits déjà rattachés', count($report['already_linked'])],
            ['Textes nettoyés', $report['texts_cleaned']],
            ['…dont textes devenus vides', $report['texts_emptied']],
            ['Conflits (non modifiés)', count($report['conflicts'])],
            ['Plusieurs codes (non modifiés)', count($report['ambiguous'])],
        ]);
        $this->line(($apply ? 'Modifications appliquées.' : 'Simulation : aucune modification enregistrée.')." Rapport : {$reportPath}");

        return self::SUCCESS;
    }

    private function restore(string $path): int
    {
        if (! File::exists($path)) {
            $this->error("Sauvegarde introuvable : {$path}");

            return self::FAILURE;
        }
        $backup = json_decode(File::get($path), true, flags: JSON_THROW_ON_ERROR);
        $apply = (bool) $this->option('apply');
        $this->line(count($backup['products']).' produit(s) et '.count($backup['created_references']).' référence(s) créée(s) dans la sauvegarde.');
        if (! $apply) {
            $this->line('Simulation : relancez avec --apply pour restaurer.');

            return self::SUCCESS;
        }
        DB::transaction(function () use ($backup) {
            foreach ($backup['products'] as $change) {
                DB::table('products')->where('id', $change['product_id'])->update([
                    'asso_product_reference_id' => $change['before']['asso_product_reference_id'],
                    'commercial_information' => $change['before']['commercial_information'],
                ]);
                $product = Product::withTrashed()->find($change['product_id']);
                foreach ($change['before']['translations'] as $translation) {
                    $product?->translations()->updateOrCreate(
                        ['locale' => $translation['locale'], 'field' => 'commercial_information'],
                        ['value' => $translation['value']]
                    );
                }
            }
            foreach ($backup['created_references'] as $id) {
                if (! Product::withTrashed()->where('asso_product_reference_id', $id)->exists()) {
                    AssoProductReference::whereKey($id)->delete();
                }
            }
        });
        $this->info('Restauration terminée.');

        return self::SUCCESS;
    }

    private function write(string $name, array $data): string
    {
        $dir = storage_path(self::DIR);
        File::ensureDirectoryExists($dir);
        $path = "{$dir}/{$name}";
        File::put($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

        return $path;
    }
}
