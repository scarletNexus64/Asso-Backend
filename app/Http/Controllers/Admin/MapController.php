<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class MapController extends Controller
{
    /**
     * Afficher la carte avec tous les utilisateurs
     */
    public function index(Request $request)
    {
        // Récupérer tous les users avec coordonnées GPS
        $query = User::whereNotNull('latitude')
            ->whereNotNull('longitude');

        // Filtres
        if ($request->filled('role')) {
            $query->where('role', $request->role);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $users = $query->get();

        // Statistiques par rôle
        $stats = [
            'total' => $users->count(),
            'vendeur' => $users->where('role', 'vendeur')->count(),
            'acheteur' => $users->where('role', 'acheteur')->count(),
            'livreur' => $users->where('role', 'livreur')->count(),
        ];

        // Centre de la carte (Yaoundé, Cameroun par défaut ou centre des users)
        if ($users->count() > 0) {
            $centerLat = $users->avg('latitude');
            $centerLng = $users->avg('longitude');
        } else {
            $centerLat = 3.8480; // Yaoundé, Cameroun
            $centerLng = 11.5021;
        }

        // Formater les données pour JavaScript
        $usersData = $users->map(function($user) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'role' => $user->role,
                'latitude' => (float) $user->latitude,
                'longitude' => (float) $user->longitude,
                'address' => $user->address,
                'avatar' => $user->avatar ? asset('storage/' . $user->avatar) : null,
            ];
        });

        return view('admin.map.index', compact('users', 'stats', 'centerLat', 'centerLng', 'usersData'));
    }

    /**
     * API pour récupérer les utilisateurs en JSON (pour AJAX)
     */
    public function getUsersData(Request $request)
    {
        $query = User::whereNotNull('latitude')
            ->whereNotNull('longitude');

        if ($request->filled('role')) {
            $query->where('role', $request->role);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $users = $query->get()->map(function($user) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'role' => $user->role,
                'latitude' => (float) $user->latitude,
                'longitude' => (float) $user->longitude,
                'address' => $user->address,
                'avatar' => $user->avatar ? asset('storage/' . $user->avatar) : null,
            ];
        });

        return response()->json($users);
    }
}
