<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class TransactionController extends Controller
{
    /**
     * Display a listing of transactions with statistics and filters.
     */
    public function index(Request $request)
    {
        // Filtres
        $query = Transaction::with(['buyer', 'seller', 'product']);

        // Filtre par méthode de paiement
        if ($request->filled('payment_method')) {
            if ($request->payment_method === 'paypal') {
                // PayPal inclut visa et mastercard
                $query->whereIn('payment_method', ['paypal', 'visa', 'mastercard']);
            } else {
                $query->where('payment_method', $request->payment_method);
            }
        }

        // Filtre par statut
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filtre par période
        $startDate = $request->filled('start_date') ? Carbon::parse($request->start_date)->startOfDay() : null;
        $endDate = $request->filled('end_date') ? Carbon::parse($request->end_date)->endOfDay() : null;

        if ($startDate && $endDate) {
            $query->whereBetween('created_at', [$startDate, $endDate]);
        } elseif ($startDate) {
            $query->where('created_at', '>=', $startDate);
        } elseif ($endDate) {
            $query->where('created_at', '<=', $endDate);
        }

        // Période de comparaison pour les statistiques
        $comparisonStartDate = $startDate ? $startDate->copy() : now()->startOfMonth();
        $comparisonEndDate = $endDate ? $endDate->copy() : now()->endOfMonth();

        // Pagination
        $transactions = $query->latest()->paginate(20)->withQueryString();

        // Statistiques globales
        $statsQuery = Transaction::query();
        if ($startDate && $endDate) {
            $statsQuery->whereBetween('created_at', [$startDate, $endDate]);
        }

        $stats = [
            'total_revenue' => $statsQuery->completed()->sum('amount'),
            'total_fees' => $statsQuery->completed()->sum('fees'),
            'net_revenue' => $statsQuery->completed()->sum('net_amount'),
            'total_transactions' => $statsQuery->completed()->count(),
            'pending_transactions' => $statsQuery->pending()->count(),
            'cancelled_transactions' => $statsQuery->cancelled()->count(),
        ];

        // Statistiques par méthode de paiement regroupées
        $paymentMethodStats = collect([
            (object)[
                'payment_method' => 'paypal',
                'label' => 'PayPal',
                'count' => $statsQuery->completed()->whereIn('payment_method', ['paypal', 'visa', 'mastercard'])->count(),
                'total' => $statsQuery->completed()->whereIn('payment_method', ['paypal', 'visa', 'mastercard'])->sum('amount'),
            ],
            (object)[
                'payment_method' => 'fedapay',
                'label' => 'FedaPay',
                'count' => $statsQuery->completed()->where('payment_method', 'fedapay')->count(),
                'total' => $statsQuery->completed()->where('payment_method', 'fedapay')->sum('amount'),
            ],
        ]);

        // Données pour le graphique (revenu par jour des 30 derniers jours)
        $chartStartDate = $comparisonStartDate->copy()->subDays(29);
        $chartEndDate = $comparisonEndDate->copy();

        $chartData = [];
        $currentDate = $chartStartDate->copy();

        while ($currentDate <= $chartEndDate) {
            $dayRevenue = Transaction::completed()
                ->whereDate('created_at', $currentDate)
                ->sum('amount');

            $chartData[] = [
                'date' => $currentDate->format('Y-m-d'),
                'label' => $currentDate->format('d/m'),
                'revenue' => $dayRevenue,
            ];

            $currentDate->addDay();
        }

        // Données pour le graphique par méthode de paiement (regroupées)
        $paymentMethodChartData = [
            'paypal' => Transaction::completed()
                ->whereIn('payment_method', ['paypal', 'visa', 'mastercard'])
                ->whereBetween('created_at', [$chartStartDate, $chartEndDate])
                ->sum('amount'),
            'fedapay' => Transaction::completed()
                ->where('payment_method', 'fedapay')
                ->whereBetween('created_at', [$chartStartDate, $chartEndDate])
                ->sum('amount'),
        ];

        return view('admin.transactions.index', compact(
            'transactions',
            'stats',
            'paymentMethodStats',
            'chartData',
            'paymentMethodChartData'
        ));
    }

    /**
     * Export transactions to Excel
     */
    public function exportExcel(Request $request)
    {
        $query = Transaction::with(['buyer', 'seller', 'product']);

        // Appliquer les mêmes filtres que l'index
        if ($request->filled('payment_method')) {
            if ($request->payment_method === 'paypal') {
                $query->whereIn('payment_method', ['paypal', 'visa', 'mastercard']);
            } else {
                $query->where('payment_method', $request->payment_method);
            }
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('start_date')) {
            $query->where('created_at', '>=', Carbon::parse($request->start_date)->startOfDay());
        }

        if ($request->filled('end_date')) {
            $query->where('created_at', '<=', Carbon::parse($request->end_date)->endOfDay());
        }

        $transactions = $query->latest()->get();

        // Créer le CSV
        $filename = 'transactions_' . now()->format('Y-m-d_H-i-s') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function() use ($transactions) {
            $file = fopen('php://output', 'w');

            // En-têtes CSV
            fputcsv($file, [
                'Référence',
                'Transaction ID',
                'Date',
                'Acheteur',
                'Vendeur',
                'Produit',
                'Montant (CFA)',
                'Frais (CFA)',
                'Net (CFA)',
                'Méthode de paiement',
                'Statut',
                'Type'
            ]);

            // Données
            foreach ($transactions as $transaction) {
                fputcsv($file, [
                    $transaction->reference,
                    $transaction->transaction_id,
                    $transaction->created_at->format('d/m/Y H:i'),
                    $transaction->buyer?->first_name . ' ' . $transaction->buyer?->last_name,
                    $transaction->seller?->first_name . ' ' . $transaction->seller?->last_name,
                    $transaction->product?->name,
                    $transaction->amount,
                    $transaction->fees,
                    $transaction->net_amount,
                    $transaction->payment_method_label,
                    $transaction->status_label,
                    $transaction->type_label
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Export transactions to PDF
     */
    public function exportPdf(Request $request)
    {
        $query = Transaction::with(['buyer', 'seller', 'product']);

        // Appliquer les mêmes filtres que l'index
        if ($request->filled('payment_method')) {
            if ($request->payment_method === 'paypal') {
                $query->whereIn('payment_method', ['paypal', 'visa', 'mastercard']);
            } else {
                $query->where('payment_method', $request->payment_method);
            }
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('start_date')) {
            $query->where('created_at', '>=', Carbon::parse($request->start_date)->startOfDay());
        }

        if ($request->filled('end_date')) {
            $query->where('created_at', '<=', Carbon::parse($request->end_date)->endOfDay());
        }

        $transactions = $query->latest()->limit(100)->get(); // Limiter à 100 pour le PDF

        // Statistiques
        $totalRevenue = $transactions->where('status', 'completed')->sum('amount');
        $totalFees = $transactions->where('status', 'completed')->sum('fees');
        $netRevenue = $transactions->where('status', 'completed')->sum('net_amount');

        // Générer le HTML pour le PDF
        $html = view('admin.transactions.pdf', compact('transactions', 'totalRevenue', 'totalFees', 'netRevenue'))->render();

        // Retourner le HTML (l'utilisateur peut imprimer en PDF depuis le navigateur)
        return response($html)->header('Content-Type', 'text/html');
    }

    /**
     * Show the specified transaction.
     */
    public function show(Transaction $transaction)
    {
        $transaction->load(['buyer', 'seller', 'product']);
        return view('admin.transactions.show', compact('transaction'));
    }
}
