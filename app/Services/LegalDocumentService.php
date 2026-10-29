<?php

namespace App\Services;

use App\Models\LegalPage;
use App\Support\Legal\DocxToHtml;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Documents Word des pages légales.
 *
 * Le fichier envoyé est converti en HTML (colonne content) et conservé sur le
 * disque public : legal-pages/{slug}/{date}__{nom}.docx. Rien n'est supprimé :
 * chaque envoi garde les fichiers précédents et sauvegarde l'ancien texte dans
 * le disque privé (legal-pages/backups/{slug}/{date}.html).
 */
class LegalDocumentService
{
    private const DIR = 'legal-pages';

    /**
     * Remplace le contenu de la page par celui du document Word.
     *
     * @param  string  $path          chemin du fichier .docx sur le serveur
     * @param  string  $originalName  nom affiché du fichier
     */
    public function replaceFromDocx(LegalPage $page, string $path, string $originalName): LegalPage
    {
        // Conversion d'abord : un fichier illisible ne touche ni la page ni le disque.
        $html = DocxToHtml::convert($path);
        if (trim(strip_tags($html)) === '') {
            throw new \RuntimeException('Le document Word ne contient aucun texte.');
        }

        $stamp = now()->format('Ymd_His');
        $this->backupContent($page, $stamp);

        $stored = Storage::disk('public')->putFileAs(
            $this->folder($page->slug),
            new \Illuminate\Http\File($path),
            $stamp . '__' . $this->safeName($originalName),
        );
        if (! $stored) {
            throw new \RuntimeException("Impossible d'enregistrer le document sur le serveur.");
        }

        $page->content = $html;
        $page->save();

        return $page;
    }

    /** Sauvegarde le texte actuel avant de le remplacer. */
    public function backupContent(LegalPage $page, ?string $stamp = null): void
    {
        $original = DB::table('legal_pages')->where('id', $page->id)->value('content');
        if ($page->exists && filled($original)) {
            Storage::disk('local')->put(
                self::DIR . '/backups/' . $page->slug . '/' . ($stamp ?? now()->format('Ymd_His')) . '.html',
                $original,
            );
        }
    }

    /**
     * Documents envoyés pour cette page, du plus récent au plus ancien.
     *
     * @return array<int, array{name: string, url: string, size: int, uploaded_at: \Illuminate\Support\Carbon|null}>
     */
    public function documents(string $slug): array
    {
        $disk = Storage::disk('public');

        return collect($disk->files($this->folder($slug)))
            ->filter(fn ($file) => str_ends_with(strtolower($file), '.docx'))
            ->sortDesc()
            ->map(function ($file) use ($disk) {
                $base = basename($file);
                [$stamp, $name] = str_contains($base, '__') ? explode('__', $base, 2) : [null, $base];
                $date = $stamp ? \Illuminate\Support\Carbon::createFromFormat('Ymd_His', $stamp) : null;

                return [
                    'name' => $name,
                    'url' => $disk->url($file),
                    'size' => $disk->size($file),
                    'uploaded_at' => $date ?: null,
                ];
            })
            ->values()
            ->all();
    }

    /** Document Word actuellement en ligne pour cette page (le plus récent). */
    public function current(string $slug): ?array
    {
        return $this->documents($slug)[0] ?? null;
    }

    /** Le slug a changé : les documents suivent la page. */
    public function renameSlug(string $from, string $to): void
    {
        if ($from === $to) {
            return;
        }
        foreach (['public' => $this->folder(''), 'local' => self::DIR . '/backups/'] as $disk => $prefix) {
            $storage = Storage::disk($disk);
            foreach ($storage->files($prefix . $from) as $file) {
                $storage->move($file, $prefix . $to . '/' . basename($file));
            }
        }
    }

    private function folder(string $slug): string
    {
        return self::DIR . '/' . $slug;
    }

    private function safeName(string $name): string
    {
        $base = Str::slug(pathinfo($name, PATHINFO_FILENAME), '_') ?: 'document';

        return Str::limit($base, 120, '') . '.docx';
    }
}
