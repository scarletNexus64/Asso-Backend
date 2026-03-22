<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class DelivererController extends Controller
{
    public function index(Request $request)
    {
        $query = User::where('role', 'livreur');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('company_name', 'like', "%{$search}%");
            });
        }

        if ($request->filled('country')) {
            $query->where('country', $request->country);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $deliverers = $query->orderBy('created_at', 'desc')->paginate(15)->withQueryString();

        return view('admin.deliverers.index', compact('deliverers'));
    }

    public function create()
    {
        return view('admin.deliverers.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'gender' => 'nullable|in:male,female,other',
            'birth_date' => 'nullable|date',
            'phone' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:100',
            'address' => 'nullable|string',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'company_name' => 'required|string|max:255',
            'company_logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048',
        ]);

        $validated['role'] = 'livreur';
        $validated['password'] = Hash::make($validated['password']);

        if ($request->hasFile('company_logo')) {
            $validated['company_logo'] = $request->file('company_logo')->store('company_logos', 'public');
        }

        User::create($validated);

        return redirect()->route('admin.deliverers.index')
            ->with('success', 'Livreur partenaire créé avec succès!');
    }

    public function show(User $deliverer)
    {
        return view('admin.deliverers.show', compact('deliverer'));
    }

    public function edit(User $deliverer)
    {
        return view('admin.deliverers.edit', compact('deliverer'));
    }

    public function update(Request $request, User $deliverer)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => ['required', 'email', Rule::unique('users')->ignore($deliverer->id)],
            'gender' => 'nullable|in:male,female,other',
            'birth_date' => 'nullable|date',
            'phone' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:100',
            'address' => 'nullable|string',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'company_name' => 'required|string|max:255',
            'company_logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048',
        ]);

        if ($request->filled('password')) {
            $request->validate(['password' => 'required|string|min:8|confirmed']);
            $validated['password'] = Hash::make($request->password);
        }

        if ($request->hasFile('company_logo')) {
            if ($deliverer->company_logo) {
                Storage::disk('public')->delete($deliverer->company_logo);
            }
            $validated['company_logo'] = $request->file('company_logo')->store('company_logos', 'public');
        }

        $deliverer->update($validated);

        return redirect()->route('admin.deliverers.index')
            ->with('success', 'Livreur partenaire mis à jour avec succès!');
    }

    public function destroy(User $deliverer)
    {
        if ($deliverer->company_logo) {
            Storage::disk('public')->delete($deliverer->company_logo);
        }

        $deliverer->delete();

        return redirect()->route('admin.deliverers.index')
            ->with('success', 'Livreur partenaire supprimé avec succès!');
    }
}
