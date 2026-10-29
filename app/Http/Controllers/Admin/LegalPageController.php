<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LegalPage;
use App\Services\LegalDocumentService;
use App\Support\Translation\ContentLocale;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Pages légales (CGU, CGV, chartes…) : rédigées dans l'éditeur ou importées
 * depuis un document Word. Le texte est servi à l'application par
 * GET /api/v1/legal-pages.
 */
class LegalPageController extends Controller
{
    /** Taille maximale du document Word, en Ko. */
    private const MAX_DOCUMENT_KB = 10240;

    public function __construct(private LegalDocumentService $documents)
    {
    }

    /**
     * Liste des pages avec aperçu du contenu.
     */
    public function index()
    {
        $legalPages = LegalPage::ordered()->get();
        $documents = $legalPages->mapWithKeys(fn (LegalPage $page) => [$page->id => $this->documents->documents($page->slug)]);

        return view('admin.legal-pages.index', compact('legalPages', 'documents'));
    }

    public function create()
    {
        return view('admin.legal-pages.create');
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'slug' => 'nullable|string|unique:legal_pages,slug',
                'content' => 'nullable|string|required_without:document',
                'document' => $this->documentRule(),
                'is_active' => 'nullable|boolean',
                'order' => 'nullable|integer|min:0',
            ] + ContentLocale::rules(['title' => 'string|max:255', 'content' => 'string']), $this->messages());

            if (empty($validated['slug'])) {
                $validated['slug'] = LegalPage::generateSlug($validated['title']);
            }

            $validated['is_active'] = $request->has('is_active');
            $validated['order'] = $validated['order'] ?? 0;
            $document = $request->file('document');
            unset($validated['document']);

            DB::transaction(function () use ($validated, $document) {
                $legalPage = LegalPage::create($validated);
                $legalPage->syncTranslations($validated['translations'] ?? null);

                if ($document) {
                    $this->replaceWith($legalPage, $document);
                }
            });

            return redirect()->route('admin.legal-pages.index', ['page' => $validated['slug']])
                ->with('success', 'Page légale créée avec succès');
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Erreur lors de la création: ' . $e->getMessage())
                ->withInput();
        }
    }

    public function edit(LegalPage $legalPage)
    {
        $documents = $this->documents->documents($legalPage->slug);

        return view('admin.legal-pages.edit', compact('legalPage', 'documents'));
    }

    public function update(Request $request, LegalPage $legalPage)
    {
        try {
            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'slug' => array_filter([
                    'required', 'string', 'unique:legal_pages,slug,' . $legalPage->id,
                    // L'application demande ces pages par leur slug : il reste figé.
                    LegalPage::isRequiredSlug($legalPage->slug) ? 'in:' . $legalPage->slug : null,
                ]),
                'content' => 'nullable|string|required_without:document',
                'document' => $this->documentRule(),
                'is_active' => 'nullable|boolean',
                'order' => 'nullable|integer|min:0',
            ] + ContentLocale::rules(['title' => 'string|max:255', 'content' => 'string']), $this->messages());

            $validated['is_active'] = $request->has('is_active');
            $validated['order'] = $validated['order'] ?? $legalPage->order;
            $document = $request->file('document');
            unset($validated['document']);

            // Un document envoyé remplace le texte de l'éditeur.
            if ($document) {
                unset($validated['content']);
            }

            $oldSlug = $legalPage->slug;
            $oldContent = $legalPage->getRawOriginal('content');

            DB::transaction(function () use ($legalPage, $validated, $document, $oldContent) {
                if (array_key_exists('content', $validated) && $validated['content'] !== $oldContent) {
                    $this->documents->backupContent($legalPage);
                }

                $legalPage->update($validated);
                $legalPage->syncTranslations($validated['translations'] ?? null);

                if ($document) {
                    $this->replaceWith($legalPage, $document);
                }
            });

            if ($oldSlug !== $legalPage->slug) {
                $this->documents->renameSlug($oldSlug, $legalPage->slug);
            }

            return redirect()->route('admin.legal-pages.index', ['page' => $legalPage->slug])
                ->with('success', $document ? 'Document importé et page mise à jour' : 'Page légale mise à jour avec succès');
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Erreur lors de la mise à jour: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Remplacer le contenu d'une page par un nouveau document Word.
     */
    public function uploadDocument(Request $request, LegalPage $legalPage)
    {
        $request->validate(['document' => 'required|' . $this->documentRule()], $this->messages());

        try {
            $this->replaceWith($legalPage, $request->file('document'));

            return redirect()->route('admin.legal-pages.index', ['page' => $legalPage->slug])
                ->with('success', 'Document importé : le contenu de « ' . $legalPage->getRawOriginal('title') . ' » a été remplacé');
        } catch (\Exception $e) {
            return redirect()->route('admin.legal-pages.index', ['page' => $legalPage->slug])
                ->with('error', "Erreur lors de l'import du document: " . $e->getMessage());
        }
    }

    public function destroy(LegalPage $legalPage)
    {
        try {
            $this->documents->backupContent($legalPage);
            $legalPage->delete();

            return redirect()->route('admin.legal-pages.index')
                ->with('success', 'Page légale supprimée avec succès');
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Erreur lors de la suppression: ' . $e->getMessage());
        }
    }

    public function toggle(LegalPage $legalPage)
    {
        try {
            $legalPage->is_active = !$legalPage->is_active;
            $legalPage->save();

            $message = $legalPage->is_active ? 'Page activée' : 'Page désactivée';

            return redirect()->back()
                ->with('success', $message);
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Erreur lors du basculement: ' . $e->getMessage());
        }
    }

    private function replaceWith(LegalPage $legalPage, UploadedFile $document): void
    {
        $this->documents->replaceFromDocx($legalPage, $document->getRealPath(), $document->getClientOriginalName());
    }

    private function documentRule(): string
    {
        return 'nullable|file|extensions:docx|max:' . self::MAX_DOCUMENT_KB;
    }

    private function messages(): array
    {
        return [
            'document.extensions' => 'Le document doit être un fichier Word (.docx).',
            'document.max' => 'Le document ne doit pas dépasser 10 Mo.',
            'content.required_without' => 'Rédigez le contenu ou importez un document Word.',
            'slug.in' => "Ce slug est utilisé par l'application pour l'acceptation obligatoire : il ne peut pas être modifié.",
        ];
    }
}
