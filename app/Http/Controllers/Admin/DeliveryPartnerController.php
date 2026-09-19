<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DelivererCompany;
use App\Models\DeliveryRoute;
use App\Models\Setting;
use App\Services\DeliveryQuoteService;
use App\Support\CountryCode;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * P4 — Partenaires logistiques : réglages de livraison (rayon des zones, TVA),
 * conditions de chaque partenaire et grilles au poids par trajet
 * (SOLEX interurbain, DHL / FedEx international vers le Cameroun).
 * Les zones urbaines restent gérées dans « Livreurs ».
 */
class DeliveryPartnerController extends Controller
{
    public function index()
    {
        $partners = DelivererCompany::withCount(['deliveryZones', 'deliveryRoutes'])
            ->orderByRaw("CASE service_type WHEN 'intercity' THEN 0 WHEN 'international' THEN 1 ELSE 2 END")
            ->orderBy('name')
            ->get();

        return view('admin.delivery_partners.index', [
            'partners' => $partners,
            'radiusKm' => DeliveryQuoteService::radiusKm(),
            'vatRate' => DeliveryQuoteService::vatRate(),
        ]);
    }

    public function updateSettings(Request $request)
    {
        $validated = $request->validate([
            'delivery_zone_radius_km' => 'required|numeric|min:0.5|max:500',
            'delivery_vat_rate' => 'required|numeric|min:0|max:100',
        ]);

        Setting::set('delivery_zone_radius_km', $validated['delivery_zone_radius_km'], 'string', 'delivery', "Rayon de couverture autour du centre d'une zone de livraison (km)");
        Setting::set('delivery_vat_rate', $validated['delivery_vat_rate'], 'string', 'delivery', 'TVA ajoutée aux grilles de livraison hors taxe (%)');

        return back()->with('success', 'Réglages de livraison enregistrés.');
    }

    public function store(Request $request)
    {
        $company = DelivererCompany::create($this->validatePartner($request) + ['is_active' => false]);

        return redirect()->route('admin.delivery-partners.edit', $company)
            ->with('success', "Partenaire « {$company->name} » créé. Ajoutez ses trajets puis activez-le.");
    }

    public function edit(DelivererCompany $partner)
    {
        $partner->load(['deliveryRoutes' => fn ($q) => $q->orderBy('origin_city')->orderBy('destination_city')]);

        return view('admin.delivery_partners.edit', [
            'partner' => $partner,
            'countries' => CountryCode::NAMES,
            'vatRate' => DeliveryQuoteService::vatRate(),
        ]);
    }

    public function update(Request $request, DelivererCompany $partner)
    {
        $partner->update($this->validatePartner($request, $partner) + [
            'is_active' => $request->boolean('is_active'),
        ]);

        return back()->with('success', 'Partenaire mis à jour.');
    }

    public function storeRoute(Request $request, DelivererCompany $partner)
    {
        $partner->deliveryRoutes()->create($this->validateRoute($request));

        return back()->with('success', 'Trajet ajouté.');
    }

    public function updateRoute(Request $request, DelivererCompany $partner, DeliveryRoute $route)
    {
        abort_unless($route->deliverer_company_id === $partner->id, 404);
        $route->update($this->validateRoute($request));

        return back()->with('success', 'Trajet « ' . $route->label() . ' » mis à jour.');
    }

    public function destroyRoute(DelivererCompany $partner, DeliveryRoute $route)
    {
        abort_unless($route->deliverer_company_id === $partner->id, 404);
        $route->delete();

        return back()->with('success', 'Trajet supprimé.');
    }

    private function validatePartner(Request $request, ?DelivererCompany $partner = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('deliverer_companies', 'name')->ignore($partner?->id)],
            'phone' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:255',
            'description' => 'nullable|string|max:1000',
            'service_type' => ['required', Rule::in(array_keys(DelivererCompany::SERVICE_TYPES))],
            'service_mode' => ['required', Rule::in(array_keys(DelivererCompany::SERVICE_MODES))],
            'conditions' => 'nullable|string|max:3000',
            'max_weight_kg' => 'nullable|numeric|min:0.1',
            'tracking_url_template' => 'nullable|string|max:255',
        ]);

        return $validated + ['prices_exclude_vat' => $request->boolean('prices_exclude_vat')];
    }

    /**
     * Trajet + grille au poids. Vente de produits : l'international est dans un seul
     * sens, de l'étranger vers le Cameroun.
     */
    private function validateRoute(Request $request): array
    {
        $countries = array_keys(CountryCode::NAMES);
        $validated = $request->validate([
            'origin_country' => ['required', Rule::in($countries)],
            'origin_city' => 'nullable|string|max:120',
            'destination_country' => ['required', Rule::in($countries)],
            'destination_city' => 'nullable|string|max:120',
            'lead_time' => 'nullable|string|max:60',
            'asso_commission' => 'nullable|numeric|min:0',
            'extra_per_kg' => 'nullable|numeric|min:0',
            'ranges' => 'required|array|min:1',
            'ranges.*.label' => 'nullable|string|max:60',
            'ranges.*.min' => 'nullable|numeric|min:0',
            'ranges.*.max' => 'nullable|numeric|min:0.001',
            'ranges.*.price' => 'nullable|numeric|min:0',
        ]);

        $ranges = collect($validated['ranges'])
            ->filter(fn ($r) => isset($r['max'], $r['price']) && $r['max'] !== '' && $r['price'] !== '')
            ->map(fn ($r) => [
                'min' => (float) ($r['min'] ?? 0),
                'max' => (float) $r['max'],
                'price' => (float) $r['price'],
                'label' => trim((string) ($r['label'] ?? '')),
            ])
            ->sortBy('max')
            ->values()
            ->all();

        if ($ranges === []) {
            throw \Illuminate\Validation\ValidationException::withMessages(['ranges' => 'Renseignez au moins une tranche (poids max et prix).']);
        }

        $international = $validated['origin_country'] !== $validated['destination_country'];
        if ($international && $validated['destination_country'] !== 'CM') {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'destination_country' => "L'international se fait de l'étranger vers le Cameroun : la destination doit être le Cameroun.",
            ]);
        }

        return [
            'origin_country' => $validated['origin_country'],
            'origin_city' => ($validated['origin_city'] ?? null) ?: null,
            'destination_country' => $validated['destination_country'],
            'destination_city' => ($validated['destination_city'] ?? null) ?: null,
            // « Et vice versa » uniquement à l'intérieur du Cameroun.
            'bidirectional' => !$international && $request->boolean('bidirectional'),
            'lead_time' => $validated['lead_time'] ?? null,
            'asso_commission' => (float) ($validated['asso_commission'] ?? 0),
            'pricing_data' => [
                'ranges' => $ranges,
                'extra_per_kg' => (float) ($validated['extra_per_kg'] ?? 0),
            ],
            'is_active' => $request->boolean('is_active', true),
        ];
    }
}
