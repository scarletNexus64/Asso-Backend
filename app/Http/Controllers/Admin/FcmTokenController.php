<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\DeviceToken;
use Illuminate\Http\Request;

class FcmTokenController extends Controller
{
    /**
     * Display a listing of users with their FCM tokens.
     */
    public function index(Request $request)
    {
        $search = $request->get('search');
        $filterStatus = $request->get('status'); // all, with_tokens, without_tokens

        // Commencer la requête
        $query = User::query()
            ->withCount(['deviceTokens', 'deviceTokens as active_tokens_count' => function ($query) {
                $query->where('is_active', true);
            }])
            ->with(['deviceTokens' => function ($query) {
                $query->where('is_active', true)->orderBy('last_used_at', 'desc');
            }]);

        // Appliquer la recherche
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        // Appliquer le filtre de statut
        if ($filterStatus === 'with_tokens') {
            $query->has('deviceTokens');
        } elseif ($filterStatus === 'without_tokens') {
            $query->doesntHave('deviceTokens');
        }

        // Récupérer les utilisateurs paginés
        $users = $query->orderBy('created_at', 'desc')->paginate(20);

        // Statistiques globales
        $stats = [
            'total_users' => User::count(),
            'users_with_tokens' => User::has('deviceTokens')->count(),
            'users_without_tokens' => User::doesntHave('deviceTokens')->count(),
            'total_tokens' => DeviceToken::count(),
            'active_tokens' => DeviceToken::where('is_active', true)->count(),
            'inactive_tokens' => DeviceToken::where('is_active', false)->count(),
        ];

        return view('admin.fcm-tokens.index', compact('users', 'stats', 'search', 'filterStatus'));
    }

    /**
     * Display detailed tokens for a specific user.
     */
    public function show(User $user)
    {
        $user->load(['deviceTokens' => function ($query) {
            $query->orderBy('created_at', 'desc');
        }]);

        return view('admin.fcm-tokens.show', compact('user'));
    }

    /**
     * Delete a specific token.
     */
    public function destroyToken(DeviceToken $token)
    {
        try {
            $token->delete();

            return redirect()->back()
                ->with('success', 'Token FCM supprimé avec succès');
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Erreur lors de la suppression du token: ' . $e->getMessage());
        }
    }

    /**
     * Toggle token activation status.
     */
    public function toggleToken(DeviceToken $token)
    {
        try {
            $token->update(['is_active' => !$token->is_active]);

            $status = $token->is_active ? 'activé' : 'désactivé';
            return redirect()->back()
                ->with('success', "Token FCM {$status} avec succès");
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Erreur lors de la modification du token: ' . $e->getMessage());
        }
    }
}
