<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Models\SalesAgent;
use App\Models\SalesCommission;
use App\Services\SalesCommissionService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * P6 — Commissions des commerciaux : à payer / payées, versement, annulation, export.
 */
class SalesCommissionController extends Controller
{
    public function __construct(protected SalesCommissionService $salesCommissionService) {}

    public function index(Request $request)
    {
        $query = $this->filtered($request);

        $totals = [
            'count' => (clone $query)->count(),
            'amount_paid' => (float) (clone $query)->sum('amount_paid_xaf'),
            'commission' => (float) (clone $query)->sum('commission_amount'),
        ];

        $commissions = $query->with(['salesAgent', 'vendor', 'paidBy'])
            ->latest('sold_at')
            ->paginate(30)
            ->withQueryString();

        $stats = [
            'due_count' => SalesCommission::due()->count(),
            'due_amount' => (float) SalesCommission::due()->sum('commission_amount'),
            'paid_count' => SalesCommission::paid()->count(),
            'paid_amount' => (float) SalesCommission::paid()->sum('commission_amount'),
        ];

        $agents = SalesAgent::withTrashed()->orderBy('last_name')->get(['id', 'first_name', 'last_name', 'code']);
        $packages = Package::orderBy('name')->get(['id', 'name']);

        return view('admin.sales.commissions.index', compact('commissions', 'stats', 'totals', 'agents', 'packages'));
    }

    /** Marque une ou plusieurs commissions « à payer » comme payées. */
    public function markPaid(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer',
            'payout_reference' => 'required|string|max:255',
        ], [
            'ids.required' => 'Sélectionnez au moins une commission à payer.',
            'payout_reference.required' => 'Indiquez la référence du versement (transaction Mobile Money, virement…).',
        ]);

        $count = $this->salesCommissionService->markPaid($validated['ids'], $validated['payout_reference'], $request->user());

        return back()->with($count > 0 ? 'success' : 'error', $count > 0
            ? "{$count} commission(s) marquée(s) payée(s)."
            : 'Aucune commission « à payer » dans la sélection.');
    }

    public function cancel(Request $request, SalesCommission $commission)
    {
        $validated = $request->validate([
            'cancel_reason' => 'required|string|max:255',
        ], [
            'cancel_reason.required' => 'Indiquez le motif de l\'annulation.',
        ]);

        $ok = $this->salesCommissionService->cancel($commission, $validated['cancel_reason']);

        return back()->with($ok ? 'success' : 'error', $ok ? 'Commission annulée.' : 'Seule une commission « à payer » peut être annulée.');
    }

    public function export(Request $request)
    {
        $commissions = $this->filtered($request)->with(['salesAgent', 'vendor'])->latest('sold_at')->get();

        $filename = 'commissions_commerciaux_'.now()->format('Y-m-d_H-i-s').'.csv';

        return response()->streamDownload(function () use ($commissions) {
            $file = fopen('php://output', 'w');
            fwrite($file, "\xEF\xBB\xBF"); // BOM UTF-8 pour Excel
            fputcsv($file, [
                'Date', 'Commercial', 'Code', 'Vendeur', 'Téléphone vendeur', 'Forfait', 'Montant payé (FCFA)',
                'Mode de paiement', 'Référence transaction', 'Taux (%)', 'Commission due (FCFA)',
                'Statut', 'Référence versement', 'Payée le',
            ], ';');

            foreach ($commissions as $c) {
                fputcsv($file, [
                    $c->sold_at->format('d/m/Y H:i'),
                    $c->salesAgent?->full_name,
                    $c->sales_code,
                    $c->vendor?->name,
                    $c->vendor?->phone,
                    $c->package_name,
                    (float) $c->amount_paid_xaf,
                    $c->payment_method,
                    $c->transaction_reference,
                    (float) $c->rate,
                    (float) $c->commission_amount,
                    $c->status_label,
                    $c->payout_reference,
                    $c->paid_at?->format('d/m/Y H:i'),
                ], ';');
            }
            fclose($file);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function filtered(Request $request): Builder
    {
        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
        ]);

        $query = SalesCommission::query();

        if (in_array($request->status, array_keys(SalesCommission::STATUS_LABELS), true)) {
            $query->where('status', $request->status);
        }
        if ($request->filled('agent_id')) {
            $query->where('sales_agent_id', (int) $request->agent_id);
        }
        if ($request->filled('package_id')) {
            $query->where('package_id', (int) $request->package_id);
        }
        if ($request->filled('start_date')) {
            $query->where('sold_at', '>=', Carbon::parse($request->start_date)->startOfDay());
        }
        if ($request->filled('end_date')) {
            $query->where('sold_at', '<=', Carbon::parse($request->end_date)->endOfDay());
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(fn ($q) => $q->where('transaction_reference', 'like', "%{$search}%")
                ->orWhere('sales_code', 'like', "%{$search}%")
                ->orWhereHas('vendor', fn ($v) => $v->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")));
        }

        return $query;
    }
}
