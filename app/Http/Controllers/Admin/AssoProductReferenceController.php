<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AssoProductReference;
use App\Models\Shop;
use Illuminate\Http\Request;

class AssoProductReferenceController extends Controller
{
    public function index()
    {
        return view('admin.product_references.index', ['references' => AssoProductReference::orderBy('name')->paginate(30)]);
    }

    public function create()
    {
        return view('admin.product_references.form', ['reference' => new AssoProductReference]);
    }

    public function edit(AssoProductReference $reference)
    {
        return view('admin.product_references.form', compact('reference'));
    }

    public function store(Request $request)
    {
        AssoProductReference::create($this->validated($request));
        return redirect()->route('admin.product-references.index')->with('success', 'Référence créée.');
    }

    public function update(Request $request, AssoProductReference $reference)
    {
        $reference->update($this->validated($request));
        return redirect()->route('admin.product-references.index')->with('success', 'Référence mise à jour.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', \Illuminate\Validation\Rule::unique('asso_product_references', 'name')->ignore($request->route('reference')?->id)],
            'address' => 'nullable|string|max:255|required_with:latitude,longitude',
            'city' => 'nullable|string|max:255|required_with:latitude,longitude',
            'country' => 'nullable|string|max:100|required_with:latitude,longitude',
            'quarter' => 'nullable|string|max:255',
            'latitude' => ['nullable', 'required_with:longitude', 'numeric', 'between:-90,90', Shop::placedRule('longitude')],
            'longitude' => 'nullable|required_with:latitude|numeric|between:-180,180',
            'is_active' => 'required|boolean',
        ]);
        return $data;
    }
}
