<?php

namespace App\Http\Controllers;

use App\Http\Middleware\SetLocale;
use App\Models\LegalPage;
use App\Services\LegalDocumentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

/**
 * Pages légales (CGU, CGV, confidentialité…) rédigées dans le back-office.
 *
 * Version web publique : lue dans l'application mobile et utilisable comme
 * lien de politique de confidentialité sur les stores. Version JSON : la liste
 * que l'application affiche dans « À propos » et le menu Aide & Support.
 */
class PublicLegalPageController extends Controller
{
    /**
     * GET /legal/{slug}?lang=en
     *
     * Page web hors API : la langue vient du paramètre lang (français par défaut).
     */
    public function show(Request $request, string $slug)
    {
        $lang = (string) $request->query('lang', 'fr');
        App::setLocale(in_array($lang, SetLocale::SUPPORTED, true) ? $lang : 'fr');

        $page = LegalPage::findActiveBySlug($slug);
        abort_unless($page, 404);

        return view('legal.show', [
            'page' => $page,
            'others' => LegalPage::getAllActive()->where('id', '!=', $page->id),
        ]);
    }

    /**
     * GET /api/v1/legal-pages
     *
     * Tous les documents actifs, avec leur contenu. Filtres facultatifs, en
     * paramètres d'URL ou dans le corps JSON (liste ou valeurs séparées par des virgules) :
     *   context      registration | purchase | vendor | delivery_partner
     *                → les documents à accepter avant cette action
     *   slug         conditions-generales-utilisation,charte-vendeurs…
     *   with_content 0 pour ne recevoir que les titres et liens (1 par défaut)
     *
     * context et slug se cumulent (union). meta.missing liste les documents
     * demandés mais introuvables ou désactivés.
     */
    public function index(Request $request)
    {
        $contexts = self::listParam($request->input('context', $request->input('contexts')));
        $unknown = array_values(array_diff($contexts, array_keys(LegalPage::CONTEXTS)));
        if ($unknown !== []) {
            return response()->json([
                'success' => false,
                'message' => 'Contexte inconnu : ' . implode(', ', $unknown),
                'available_contexts' => array_keys(LegalPage::CONTEXTS),
            ], 422);
        }

        $slugs = self::listParam($request->input('slug', $request->input('slugs')));
        foreach ($contexts as $context) {
            $slugs = array_merge($slugs, LegalPage::CONTEXTS[$context]['slugs']);
        }
        $slugs = array_values(array_unique($slugs));
        $filtered = $contexts !== [] || $slugs !== [];

        $withContent = filter_var($request->input('with_content', true), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? true;

        $pages = LegalPage::getAllActive();
        if ($filtered) {
            $pages = $pages->whereIn('slug', $slugs);
        }

        return response()->json([
            'success' => true,
            'data' => $pages
                ->map(fn (LegalPage $page) => self::summary($page) + ($withContent ? ['content' => $page->content] : []))
                ->values(),
            'meta' => [
                'filters' => ['context' => $contexts, 'slug' => $filtered ? $slugs : []],
                'missing' => $filtered ? array_values(array_diff($slugs, $pages->pluck('slug')->all())) : [],
                'contexts' => self::contexts(),
            ],
        ]);
    }

    /**
     * GET /api/v1/legal-pages/{slug}
     */
    public function showJson(string $slug)
    {
        $page = LegalPage::findActiveBySlug($slug);
        if (!$page) {
            return response()->json(['success' => false, 'message' => __('legal.not_found')], 404);
        }

        return response()->json([
            'success' => true,
            'data' => self::summary($page) + ['content' => $page->content],
        ]);
    }

    /** Contextes d'acceptation obligatoire, avec les documents demandés. */
    public static function contexts(): array
    {
        return collect(LegalPage::CONTEXTS)
            ->map(fn (array $context, string $key) => ['key' => $key, 'label' => $context['label'], 'slugs' => $context['slugs']])
            ->values()
            ->all();
    }

    /** « a,b », [« a », « b »] ou null → [« a », « b »]. */
    private static function listParam(mixed $value): array
    {
        $items = is_array($value) ? $value : explode(',', (string) $value);

        return array_values(array_unique(array_filter(array_map(
            fn ($item) => is_scalar($item) ? strtolower(trim((string) $item)) : '',
            $items,
        ))));
    }

    /** Lien public de la page, dans la langue courante. */
    public static function url(string $slug): string
    {
        $locale = App::getLocale();

        return route('legal.show', $locale === 'fr' ? $slug : ['slug' => $slug, 'lang' => $locale]);
    }

    public static function summary(LegalPage $page): array
    {
        return [
            'slug' => $page->slug,
            'title' => $page->title,
            'url' => self::url($page->slug),
            // Document Word d'origine, quand la page a été importée.
            'document_url' => app(LegalDocumentService::class)->current($page->slug)['url'] ?? null,
            // Actions pour lesquelles l'utilisateur doit accepter ce document.
            'required_for' => LegalPage::contextsFor($page->slug),
            'updated_at' => $page->updated_at?->toIso8601String(),
        ];
    }
}
