<?php

namespace App\Console\Commands;

use App\Models\LegalPage;
use App\Services\LegalDocumentService;
use App\Support\Legal\DocxToHtml;
use Illuminate\Console\Command;

/**
 * Importe un document Word dans une page légale.
 *
 * La page est créée si le slug n'existe pas, sinon seul son contenu est
 * remplacé (l'ancien texte est sauvegardé, rien n'est supprimé).
 *
 *   php artisan legal-pages:import "docs/CGU & Chartes/ASSO_Charte_Vendeur_Final.docx" charte-vendeurs
 */
class ImportLegalDocument extends Command
{
    protected $signature = 'legal-pages:import
        {file : Chemin du fichier .docx}
        {slug : Slug de la page légale}
        {--title= : Titre (obligatoire pour une nouvelle page, ignoré sinon)}
        {--order=0 : Ordre d\'affichage d\'une nouvelle page}
        {--dry-run : Affiche le résultat de la conversion sans rien enregistrer}';

    protected $description = "Importe un document Word (.docx) dans une page légale";

    public function handle(LegalDocumentService $documents): int
    {
        $file = $this->argument('file');
        $slug = $this->argument('slug');

        if (! is_file($file) || ! str_ends_with(strtolower($file), '.docx')) {
            $this->error("Fichier .docx introuvable : {$file}");

            return self::FAILURE;
        }

        $page = LegalPage::findBySlug($slug);

        if ($this->option('dry-run')) {
            $html = DocxToHtml::convert($file);
            $this->info(($page ? 'Mise à jour de « ' . $page->getRawOriginal('title') . ' »' : 'Nouvelle page') . " ({$slug}) : " . mb_strlen(strip_tags($html)) . ' caractères');
            $this->line(mb_substr($html, 0, 600) . '…');

            return self::SUCCESS;
        }

        if (! $page) {
            if (! $this->option('title')) {
                $this->error('--title est obligatoire pour créer une nouvelle page.');

                return self::FAILURE;
            }
            $page = new LegalPage([
                'slug' => $slug,
                'title' => $this->option('title'),
                'content' => '',
                'is_active' => true,
                'order' => (int) $this->option('order'),
            ]);
        }

        $created = ! $page->exists;
        $documents->replaceFromDocx($page, $file, basename($file));

        $this->info(($created ? 'Page créée' : 'Contenu remplacé') . " : {$slug} (#{$page->id})");

        return self::SUCCESS;
    }
}
