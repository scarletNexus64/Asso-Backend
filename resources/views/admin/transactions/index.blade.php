@extends('admin.layouts.app')

@section('content')
<div class="p-6">
    <!-- Header -->
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-white">Transactions</h1>
            <p class="text-gray-400">Gérez et analysez toutes les transactions de paiement</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('admin.transactions.export-excel', request()->query()) }}"
               class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors">
                <i class="fas fa-file-excel mr-2"></i>
                Excel
            </a>
            <a href="{{ route('admin.transactions.export-pdf', request()->query()) }}"
               target="_blank"
               class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors">
                <i class="fas fa-file-pdf mr-2"></i>
                PDF
            </a>
        </div>
    </div>

    <!-- Filters -->
    <div class="mb-6 bg-dark-100 rounded-xl shadow-lg border border-dark-200 p-6">
        <form method="GET" action="{{ route('admin.transactions.index') }}" class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Date de début</label>
                <input type="date" name="start_date" value="{{ request('start_date') }}"
                       class="w-full px-4 py-2 bg-dark-50 border border-dark-300 rounded-lg text-white focus:ring-2 focus:ring-primary-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Date de fin</label>
                <input type="date" name="end_date" value="{{ request('end_date') }}"
                       class="w-full px-4 py-2 bg-dark-50 border border-dark-300 rounded-lg text-white focus:ring-2 focus:ring-primary-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Méthode de paiement</label>
                <select name="payment_method"
                        class="w-full px-4 py-2 bg-dark-50 border border-dark-300 rounded-lg text-white focus:ring-2 focus:ring-primary-500">
                    <option value="">Toutes</option>
                    <option value="paypal" {{ in_array(request('payment_method'), ['paypal', 'visa', 'mastercard']) ? 'selected' : '' }}>PayPal (Visa, MasterCard)</option>
                    <option value="fedapay" {{ request('payment_method') == 'fedapay' ? 'selected' : '' }}>FedaPay</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Statut</label>
                <select name="status"
                        class="w-full px-4 py-2 bg-dark-50 border border-dark-300 rounded-lg text-white focus:ring-2 focus:ring-primary-500">
                    <option value="">Tous</option>
                    <option value="completed" {{ request('status') == 'completed' ? 'selected' : '' }}>Complété</option>
                    <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>En attente</option>
                    <option value="cancelled" {{ request('status') == 'cancelled' ? 'selected' : '' }}>Annulé</option>
                </select>
            </div>
            <div class="md:col-span-4 flex gap-2">
                <button type="submit"
                        class="px-6 py-2 bg-gradient-to-r from-primary-500 to-primary-600 text-white rounded-lg hover:shadow-lg transition-all">
                    <i class="fas fa-search mr-2"></i> Filtrer
                </button>
                <a href="{{ route('admin.transactions.index') }}"
                   class="px-6 py-2 bg-dark-300 text-white rounded-lg hover:bg-dark-400 transition-all">
                    <i class="fas fa-redo mr-2"></i> Réinitialiser
                </a>
            </div>
        </form>
    </div>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
        <div class="bg-gradient-to-br from-blue-500/10 to-blue-600/10 border border-blue-500/30 rounded-xl p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-400 mb-1">Revenu Total</p>
                    <p class="text-2xl font-bold text-white">{{ number_format($stats['total_revenue'], 0, ',', ' ') }} <span class="text-sm">XOF</span></p>
                </div>
                <div class="w-12 h-12 bg-blue-500/20 rounded-lg flex items-center justify-center">
                    <i class="fas fa-money-bill-wave text-blue-500 text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-gradient-to-br from-purple-500/10 to-purple-600/10 border border-purple-500/30 rounded-xl p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-400 mb-1">Transactions</p>
                    <p class="text-2xl font-bold text-white">{{ $stats['total_transactions'] }}</p>
                    <p class="text-xs text-gray-500 mt-1">
                        {{ $stats['pending_transactions'] }} en attente · {{ $stats['cancelled_transactions'] }} annulées
                    </p>
                </div>
                <div class="w-12 h-12 bg-purple-500/20 rounded-lg flex items-center justify-center">
                    <i class="fas fa-receipt text-purple-500 text-xl"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <!-- Revenue Chart -->
        <div class="bg-dark-100 rounded-xl shadow-lg border border-dark-200 p-6">
            <h3 class="text-lg font-semibold text-white mb-4 flex items-center">
                <i class="fas fa-chart-area text-primary-500 mr-2"></i>
                Évolution du Revenu (30 derniers jours)
            </h3>
            <div style="position: relative; height: 250px;">
                <canvas id="revenueChart"></canvas>
            </div>
        </div>

        <!-- Payment Methods Chart -->
        <div class="bg-dark-100 rounded-xl shadow-lg border border-dark-200 p-6">
            <h3 class="text-lg font-semibold text-white mb-4 flex items-center">
                <i class="fas fa-chart-pie text-primary-500 mr-2"></i>
                Répartition par Méthode de Paiement
            </h3>
            <div style="position: relative; height: 250px;">
                <canvas id="paymentMethodsChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Payment Methods Statistics -->
    <div class="mb-6 bg-dark-100 rounded-xl shadow-lg border border-dark-200 p-6">
        <h3 class="text-lg font-semibold text-white mb-4">Statistiques par Méthode de Paiement</h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <!-- Total -->
            <div class="bg-gradient-to-br from-primary-500/10 to-primary-600/10 border border-primary-500/30 rounded-lg p-4">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-sm text-gray-400">
                        <i class="fas fa-chart-bar text-primary-500 mr-1"></i> TOTAL
                    </span>
                    <span class="text-xs bg-primary-500/20 text-primary-300 px-2 py-1 rounded">{{ $stats['total_transactions'] }}</span>
                </div>
                <p class="text-2xl font-bold text-white">{{ number_format($stats['total_revenue'], 0, ',', ' ') }} <span class="text-sm text-gray-400">XOF</span></p>
            </div>

            @foreach($paymentMethodStats as $stat)
                <div class="bg-dark-50 rounded-lg p-4 border border-dark-300">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-sm text-gray-400">
                            @if($stat->payment_method == 'paypal')
                                <i class="fab fa-paypal text-blue-500 mr-1"></i> PayPal
                            @elseif($stat->payment_method == 'fedapay')
                                <i class="fas fa-credit-card text-green-500 mr-1"></i> FedaPay
                            @endif
                        </span>
                        <span class="text-xs bg-primary-500/20 text-primary-300 px-2 py-1 rounded">{{ $stat->count }}</span>
                    </div>
                    <p class="text-lg font-bold text-white">{{ number_format($stat->total, 0, ',', ' ') }} <span class="text-sm text-gray-400">XOF</span></p>
                </div>
            @endforeach
        </div>
    </div>

    <!-- Transactions Table -->
    <div class="bg-dark-100 rounded-xl shadow-lg border border-dark-200">
        @if($transactions->count() > 0)
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-dark-200">
                    <thead class="bg-dark-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-white uppercase tracking-wider">Référence</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-white uppercase tracking-wider">Date</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-white uppercase tracking-wider">Acheteur</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-white uppercase tracking-wider">Montant</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-white uppercase tracking-wider">Méthode</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-white uppercase tracking-wider">Statut</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold text-white uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-dark-100 divide-y divide-dark-200">
                        @foreach($transactions as $transaction)
                            <tr class="hover:bg-dark-50 transition-colors">
                                <td class="px-6 py-4">
                                    <div class="text-sm">
                                        <div class="font-medium text-white">{{ $transaction->reference }}</div>
                                        <div class="text-xs text-gray-500">{{ $transaction->transaction_id }}</div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-300">
                                    {{ $transaction->created_at->format('d/m/Y H:i') }}
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-300">
                                    {{ $transaction->buyer?->first_name }} {{ $transaction->buyer?->last_name }}
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm">
                                        <div class="font-medium text-white">{{ number_format($transaction->amount, 0, ',', ' ') }} XOF</div>
                                        <div class="text-xs text-gray-500">Frais: {{ number_format($transaction->fees, 0, ',', ' ') }} XOF</div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="px-3 py-1 text-xs font-semibold rounded-full bg-{{ $transaction->payment_method_color }}-500/20 text-{{ $transaction->payment_method_color }}-300 border border-{{ $transaction->payment_method_color }}-500/50">
                                        <i class="fab {{ $transaction->payment_method_icon }} mr-1"></i>
                                        {{ $transaction->payment_method_label }}
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    @if($transaction->status == 'completed')
                                        <span class="px-3 py-1 text-xs font-semibold rounded-full bg-green-500/20 text-green-300 border border-green-500/50">
                                            <i class="fas fa-check-circle mr-1"></i> Complété
                                        </span>
                                    @elseif($transaction->status == 'pending')
                                        <span class="px-3 py-1 text-xs font-semibold rounded-full bg-yellow-500/20 text-yellow-300 border border-yellow-500/50">
                                            <i class="fas fa-clock mr-1"></i> En attente
                                        </span>
                                    @else
                                        <span class="px-3 py-1 text-xs font-semibold rounded-full bg-red-500/20 text-red-300 border border-red-500/50">
                                            <i class="fas fa-times-circle mr-1"></i> Annulé
                                        </span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <a href="{{ route('admin.transactions.show', $transaction) }}"
                                       class="px-3 py-1.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors text-xs"
                                       title="Voir">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="px-6 py-4 border-t border-dark-200">
                {{ $transactions->links() }}
            </div>
        @else
            <div class="text-center py-12 text-gray-400">
                <i class="fas fa-receipt text-6xl text-gray-600 mb-4"></i>
                <p class="text-lg">Aucune transaction trouvée</p>
            </div>
        @endif
    </div>
</div>

@push('scripts')
<script>
// Revenue Chart
const revenueCtx = document.getElementById('revenueChart').getContext('2d');
const revenueChart = new Chart(revenueCtx, {
    type: 'line',
    data: {
        labels: @json(array_column($chartData, 'label')),
        datasets: [{
            label: 'Revenu (CFA)',
            data: @json(array_column($chartData, 'revenue')),
            borderColor: '#f97316',
            backgroundColor: 'rgba(249, 115, 22, 0.1)',
            borderWidth: 2,
            fill: true,
            tension: 0.4
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        animation: {
            duration: 0
        },
        plugins: {
            legend: {
                labels: {
                    color: '#fff'
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    color: '#9ca3af',
                    callback: function(value) {
                        return new Intl.NumberFormat('fr-FR').format(value) + ' CFA';
                    }
                },
                grid: {
                    color: 'rgba(255, 255, 255, 0.05)'
                }
            },
            x: {
                ticks: {
                    color: '#9ca3af'
                },
                grid: {
                    color: 'rgba(255, 255, 255, 0.05)'
                }
            }
        }
    }
});

// Payment Methods Chart
const paymentCtx = document.getElementById('paymentMethodsChart').getContext('2d');
const paymentChart = new Chart(paymentCtx, {
    type: 'doughnut',
    data: {
        labels: ['PayPal (Visa, MasterCard)', 'FedaPay'],
        datasets: [{
            data: @json(array_values($paymentMethodChartData)),
            backgroundColor: [
                'rgba(59, 130, 246, 0.8)',
                'rgba(34, 197, 94, 0.8)'
            ],
            borderColor: [
                'rgba(59, 130, 246, 1)',
                'rgba(34, 197, 94, 1)'
            ],
            borderWidth: 2
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        animation: {
            duration: 0
        },
        plugins: {
            legend: {
                position: 'bottom',
                labels: {
                    color: '#fff',
                    padding: 15
                }
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return context.label + ': ' + new Intl.NumberFormat('fr-FR').format(context.parsed) + ' CFA';
                    }
                }
            }
        }
    }
});
</script>
@endpush
@endsection
