<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AffiliateSetting;
use App\Models\AffiliateCommission;
use App\Models\User;
use Illuminate\Http\Request;

class AffiliateController extends Controller
{
    /**
     * Afficher la page de configuration
     */
    public function settings()
    {
        $settings = AffiliateSetting::getSettings();

        // Statistiques
        $stats = [
            'total_affiliates' => User::whereNotNull('referred_by_id')->count(),
            'total_commissions' => AffiliateCommission::sum('amount'),
            'pending_commissions' => AffiliateCommission::pending()->sum('amount'),
            'paid_commissions' => AffiliateCommission::paid()->sum('amount'),
            'active_referrers' => User::has('referrals')->count(),
        ];

        return view('admin.affiliate.settings', compact('settings', 'stats'));
    }

    /**
     * Mettre à jour la configuration
     */
    public function updateSettings(Request $request)
    {
        $validated = $request->validate([
            'is_enabled' => 'boolean',
            'max_levels' => 'required|integer|min:1|max:3',
            'level_1_percentage' => 'required|numeric|min:0|max:100',
            'level_2_percentage' => 'required|numeric|min:0|max:100',
            'level_3_percentage' => 'required|numeric|min:0|max:100',
            'minimum_withdrawal' => 'required|numeric|min:0',
            'auto_approve_commissions' => 'boolean',
            'terms_and_conditions' => 'nullable|string',
        ]);

        $validated['is_enabled'] = $request->has('is_enabled');
        $validated['auto_approve_commissions'] = $request->has('auto_approve_commissions');

        $settings = AffiliateSetting::getSettings();
        $settings->update($validated);

        return redirect()->back()->with('success', 'Configuration mise à jour avec succès.');
    }

    /**
     * Afficher l'arbre d'affiliation
     */
    public function tree()
    {
        $settings = AffiliateSetting::getSettings();

        // Récupérer les utilisateurs de niveau 1 (qui ont des filleuls)
        $topReferrers = User::withCount('referrals')
            ->has('referrals')
            ->orderBy('referrals_count', 'desc')
            ->limit(20)
            ->get();

        return view('admin.affiliate.tree', compact('topReferrers', 'settings'));
    }

    /**
     * Obtenir les données de l'arbre d'un utilisateur (AJAX)
     */
    public function getTreeData(User $user)
    {
        $tree = $this->buildTree($user, 1);

        return response()->json($tree);
    }

    private function buildTree(User $user, $level)
    {
        $settings = AffiliateSetting::getSettings();

        if ($level > $settings->max_levels) {
            return [];
        }

        $referrals = $user->referrals()
            ->withCount('referrals')
            ->with(['commissionsEarned' => function($q) {
                $q->where('status', 'paid');
            }])
            ->get();

        return $referrals->map(function($referral) use ($level, $settings) {
            $totalEarned = $referral->commissionsEarned->sum('amount');

            return [
                'id' => $referral->id,
                'name' => $referral->name,
                'email' => $referral->email,
                'referral_code' => $referral->referral_code,
                'level' => $level,
                'total_referrals' => $referral->referrals_count,
                'total_earned' => number_format($totalEarned, 0, ',', ' ') . ' XOF',
                'joined_at' => $referral->created_at->format('d/m/Y'),
                'children' => $level < $settings->max_levels ? $this->buildTree($referral, $level + 1) : [],
            ];
        })->toArray();
    }

    /**
     * Liste des commissions
     */
    public function commissions(Request $request)
    {
        $query = AffiliateCommission::with(['affiliate', 'referredUser', 'transaction'])->recent();

        // Filtres
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('level')) {
            $query->byLevel($request->level);
        }

        if ($request->filled('search')) {
            $query->whereHas('affiliate', function($q) use ($request) {
                $q->where('first_name', 'like', '%' . $request->search . '%')
                  ->orWhere('last_name', 'like', '%' . $request->search . '%')
                  ->orWhere('email', 'like', '%' . $request->search . '%');
            });
        }

        $commissions = $query->paginate(20);

        // Stats
        $stats = [
            'total' => AffiliateCommission::count(),
            'pending' => AffiliateCommission::pending()->count(),
            'approved' => AffiliateCommission::approved()->count(),
            'paid' => AffiliateCommission::paid()->count(),
            'rejected' => AffiliateCommission::rejected()->count(),
            'total_amount' => AffiliateCommission::sum('amount'),
        ];

        return view('admin.affiliate.commissions', compact('commissions', 'stats'));
    }

    /**
     * Approuver une commission
     */
    public function approveCommission(AffiliateCommission $commission)
    {
        $commission->update(['status' => 'approved']);

        return redirect()->back()->with('success', 'Commission approuvée.');
    }

    /**
     * Marquer comme payée
     */
    public function payCommission(AffiliateCommission $commission)
    {
        $commission->update([
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        // Mettre à jour les earnings de l'affilié
        $affiliate = $commission->affiliate;
        $affiliate->increment('withdrawn_earnings', $commission->amount);
        $affiliate->decrement('pending_earnings', $commission->amount);

        return redirect()->back()->with('success', 'Commission marquée comme payée.');
    }

    /**
     * Rejeter une commission
     */
    public function rejectCommission(AffiliateCommission $commission)
    {
        $commission->update(['status' => 'rejected']);

        return redirect()->back()->with('success', 'Commission rejetée.');
    }
}
