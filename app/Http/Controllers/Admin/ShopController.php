<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Support\Str;

class ShopController extends Controller
{
    /**
     * Display a listing of shops
     */
    public function index(Request $request)
    {
        $query = Shop::with('user', 'products');

        // Search filter
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhereHas('user', function($userQuery) use ($search) {
                      $userQuery->where('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%");
                  });
            });
        }

        // Status filter
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // User filter
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        $shops = $query->latest()->paginate(15)->withQueryString();
        $users = User::where('role', 'vendeur')->get();

        return view('admin.shops.index', compact('shops', 'users'));
    }

    /**
     * Show the form for creating a new shop
     */
    public function create()
    {
        $users = User::whereIn('role', ['vendeur', 'client'])->get();
        return view('admin.shops.create', compact('users'));
    }

    /**
     * Store a newly created shop
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'shop_link' => 'nullable|url',
            'address' => 'nullable|string',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'status' => 'required|in:active,inactive',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
        ]);

        $validated['slug'] = Str::slug($validated['name']) . '-' . time();

        // Handle logo upload
        if ($request->hasFile('logo')) {
            $logo = $request->file('logo');
            $logoName = time() . '_' . Str::slug($validated['name']) . '.' . $logo->getClientOriginalExtension();
            $logo->move(public_path('storage/shops'), $logoName);
            $validated['logo'] = 'storage/shops/' . $logoName;
        }

        Shop::create($validated);

        return redirect()->route('admin.shops.index')
            ->with('success', 'Boutique créée avec succès!');
    }

    /**
     * Display the specified shop
     */
    public function show(Shop $shop)
    {
        $shop->load('user', 'products');
        return view('admin.shops.show', compact('shop'));
    }

    /**
     * Show the form for editing the specified shop
     */
    public function edit(Shop $shop)
    {
        $users = User::whereIn('role', ['vendeur', 'client'])->get();
        return view('admin.shops.edit', compact('shop', 'users'));
    }

    /**
     * Update the specified shop
     */
    public function update(Request $request, Shop $shop)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'shop_link' => 'nullable|url',
            'address' => 'nullable|string',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'status' => 'required|in:active,inactive',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
        ]);

        // Update slug only if name changed
        if ($shop->name !== $validated['name']) {
            $validated['slug'] = Str::slug($validated['name']) . '-' . time();
        }

        // Handle logo upload
        if ($request->hasFile('logo')) {
            // Delete old logo if exists
            if ($shop->logo && file_exists(public_path($shop->logo))) {
                unlink(public_path($shop->logo));
            }

            $logo = $request->file('logo');
            $logoName = time() . '_' . Str::slug($validated['name']) . '.' . $logo->getClientOriginalExtension();
            $logo->move(public_path('storage/shops'), $logoName);
            $validated['logo'] = 'storage/shops/' . $logoName;
        }

        $shop->update($validated);

        return redirect()->route('admin.shops.index')
            ->with('success', 'Boutique mise à jour avec succès!');
    }

    /**
     * Remove the specified shop
     */
    public function destroy(Shop $shop)
    {
        // Delete logo if exists
        if ($shop->logo && file_exists(public_path($shop->logo))) {
            unlink(public_path($shop->logo));
        }

        $shop->delete();

        return redirect()->route('admin.shops.index')
            ->with('success', 'Boutique supprimée avec succès!');
    }
}
