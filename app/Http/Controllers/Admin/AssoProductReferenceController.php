<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AssoProductReference;
use App\Models\Product;
use App\Models\Shop;
use App\Support\AssoReferenceCode;
use App\Support\AssoScope;
use Illuminate\Http\Request;

class AssoProductReferenceController extends Controller
{
    public function index(Request $request)
    {
        $query = AssoProductReference::withCount('products')->orderBy('name');

        if ($request->filled('search')) {
            $term = '%'.mb_strtolower(trim((string) $request->input('search'))).'%';
            $query->where(fn ($q) => $q->whereRaw('lower(name) like ?', [$term])
                ->orWhereRaw('lower(coalesce(city, \'\')) like ?', [$term])
                ->orWhereRaw('lower(coalesce(quarter, \'\')) like ?', [$term]));
        }
        if (in_array($request->input('status'), ['active', 'inactive'], true)) {
            $query->where('is_active', $request->input('status') === 'active');
        }
        $references = $query->get();
        if (in_array($request->input('location'), ['located', 'shop'], true)) {
            $located = $request->input('location') === 'located';
            $references = $references->filter(fn ($reference) => $reference->isLocated() === $located)->values();
        }

        $all = AssoProductReference::all();
        $assoProducts = Product::whereIn('shop_id', Shop::where('user_id', AssoScope::ownerId())->select('id'));
        $stats = [
            'references' => $all->count(),
            'linked' => (clone $assoProducts)->whereNotNull('asso_product_reference_id')->count(),
            'unlinked' => (clone $assoProducts)->whereNull('asso_product_reference_id')->count(),
            'not_located' => $all->reject->isLocated()->count(),
        ];

        return view('admin.product_references.index', compact('references', 'stats'));
    }

    public function create()
    {
        return view('admin.product_references.form', ['reference' => new AssoProductReference]);
    }

    public function edit(AssoProductReference $reference)
    {
        $reference->loadCount('products');

        return view('admin.product_references.form', compact('reference'));
    }

    public function store(Request $request)
    {
        $reference = AssoProductReference::create($this->validated($request));

        return redirect()->route('admin.product-references.index')->with('success', "Référence {$reference->name} créée.");
    }

    public function update(Request $request, AssoProductReference $reference)
    {
        $reference->update($this->validated($request, $reference));

        return redirect()->route('admin.product-references.index')->with('success', "Référence {$reference->name} mise à jour.");
    }

    /** Active ou désactive depuis la liste ; les produits déjà rattachés la conservent. */
    public function toggleStatus(AssoProductReference $reference)
    {
        $reference->update(['is_active' => ! $reference->is_active]);

        return back()->with('success', $reference->is_active
            ? "Référence {$reference->name} réactivée : elle est de nouveau proposée aux produits."
            : "Référence {$reference->name} désactivée : elle n'est plus proposée aux nouveaux produits.");
    }

    private function validated(Request $request, ?AssoProductReference $reference = null): array
    {
        // Le code est conservé tel que fourni : seuls les espaces sont retirés et la casse unifiée.
        $request->merge(['name' => AssoReferenceCode::normalize((string) $request->input('name'))]);

        return $request->validate([
            'name' => ['required', 'string', 'max:255', function ($attribute, $value, $fail) use ($reference) {
                if (AssoProductReference::named($value)->when($reference, fn ($q) => $q->whereKeyNot($reference->id))->exists()) {
                    $fail('Cette référence existe déjà.');
                }
            }],
            'address' => 'nullable|string|max:255|required_with:latitude,longitude',
            'city' => 'nullable|string|max:255|required_with:latitude,longitude',
            'country' => 'nullable|string|max:100|required_with:latitude,longitude',
            'quarter' => 'nullable|string|max:255',
            'latitude' => ['nullable', 'required_with:longitude', 'numeric', 'between:-90,90', Shop::placedRule('longitude')],
            'longitude' => 'nullable|required_with:latitude|numeric|between:-180,180',
            'is_active' => 'required|boolean',
        ], [
            'name.required' => 'Indiquez le code de la référence.',
            'address.required_with' => "Placez l'adresse sur la carte avec la position.",
            'city.required_with' => 'Indiquez la ville de la référence géolocalisée.',
            'country.required_with' => 'Indiquez le pays de la référence géolocalisée.',
        ]);
    }
}
