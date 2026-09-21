<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Models\ProductBoost;
use App\Models\Setting;
use App\Services\ProductBoostService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Asso Ads — supervision des campagnes de sponsoring dans l'administration.
 *
 * La grille tarifaire elle-même (prix, durée, quota d'impressions) se gère
 * depuis Packages → type « Boost Sponsoring » : un forfait boost est un
 * package comme les autres. Cet écran montre ce qui est vendu et diffusé,
 * et règle la diffusion (activation, nombre et position des emplacements).
 */
class ProductBoostController extends Controller
{
    private const STATUSES = [
        ProductBoost::ACTIVE => 'En diffusion',
        ProductBoost::COMPLETED => 'Terminée (audience atteinte)',
        ProductBoost::EXPIRED => 'Terminée (échéance)',
        ProductBoost::CANCELLED => 'Annulée',
    ];

    public function __construct(private ProductBoostService $boosts)
    {
    }

    public function index(Request $request)
    {
        $status = array_key_exists($request->query('status'), self::STATUSES)
            ? $request->query('status')
            : null;
        $search = trim((string) $request->query('search', ''));

        $campaigns = ProductBoost::with(['product:id,name,shop_id', 'shop:id,name', 'user:id,first_name,last_name', 'package:id,name'])
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($search !== '', fn ($q) => $q->whereHas(
                'product',
                fn ($p) => $p->whereRaw('LOWER(name) LIKE ?', ['%' . mb_strtolower($search) . '%'])
            ))
            ->latest('starts_at')
            ->paginate(20)
            ->withQueryString();

        // Chiffres cumulés : ce que le sponsoring rapporte et ce qu'il délivre.
        $totals = DB::table('product_boosts')
            ->selectRaw('COUNT(*) AS campaigns')
            // `FILTER (WHERE …)` n'existe que sur PostgreSQL : un CASE reste
            // lisible et fonctionne aussi en tests (SQLite) et sur MySQL.
            ->selectRaw("COALESCE(SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END), 0) AS running")
            ->selectRaw('COALESCE(SUM(amount_xaf), 0) AS revenue')
            ->selectRaw('COALESCE(SUM(impressions_served), 0) AS impressions')
            ->selectRaw('COALESCE(SUM(impressions_quota), 0) AS quota')
            ->selectRaw('COALESCE(SUM(clicks), 0) AS clicks')
            ->first();

        return view('admin.ads.index', [
            'campaigns' => $campaigns,
            'statuses' => self::STATUSES,
            'status' => $status,
            'search' => $search,
            'totals' => $totals,
            'packagesCount' => Package::ofType('boost')->active()->count(),
            'settings' => [
                'enabled' => $this->boosts->enabled(),
                'slots_per_page' => $this->boosts->slotsPerPage(),
                'slot_positions' => implode(',', $this->boosts->slotPositions()),
            ],
        ]);
    }

    public function show(ProductBoost $boost)
    {
        $boost->load(['product.primaryImage', 'shop', 'user', 'package', 'subscription']);

        return view('admin.ads.show', [
            'boost' => $boost,
            'summary' => $this->boosts->campaignSummary($boost),
        ]);
    }

    /**
     * Réglages de diffusion.
     *
     * `ads_enabled` coupe net la diffusion sans toucher aux campagnes : elles
     * restent actives et reprendront à la réactivation. C'est l'interrupteur à
     * utiliser en cas de problème, plutôt que d'annuler des campagnes payées.
     */
    public function updateSettings(Request $request)
    {
        $validated = $request->validate([
            'slots_per_page' => 'required|integer|min:0|max:10',
            'slot_positions' => 'nullable|string|max:120',
        ]);

        $positions = collect(explode(',', (string) ($validated['slot_positions'] ?? '')))
            ->map(fn ($v) => (int) trim($v))
            ->filter(fn ($v) => $v >= 0)
            ->unique()
            ->sort()
            ->values();

        Setting::set('ads_enabled', $request->boolean('enabled') ? '1' : '0', 'boolean', 'ads', 'Diffusion des produits sponsorisés');
        Setting::set('ads_slots_per_page', (string) $validated['slots_per_page'], 'integer', 'ads', 'Emplacements sponsorisés par page');
        Setting::set(
            'ads_slot_positions',
            $positions->isNotEmpty() ? $positions->implode(',') : implode(',', ProductBoostService::DEFAULT_SLOT_POSITIONS),
            'string',
            'ads',
            'Positions des emplacements sponsorisés dans la page'
        );

        return redirect()->route('admin.ads.index')->with('success', 'Réglages Asso Ads mis à jour.');
    }

    /**
     * Arrêt d'une campagne par l'administration (litige, produit retiré…).
     * Sans remboursement automatique : la portée déjà délivrée a été consommée.
     */
    public function cancel(ProductBoost $boost)
    {
        if ($boost->status !== ProductBoost::ACTIVE) {
            return redirect()->back()->with('error', "Cette campagne n'est plus en cours.");
        }

        // L'administration coupe : le vendeur doit l'apprendre autrement qu'en
        // constatant la disparition de son annonce.
        $this->boosts->cancel($boost, notify: true);

        return redirect()->back()->with('success', 'Campagne arrêtée. Le vendeur a été prévenu.');
    }
}
