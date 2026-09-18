@extends('admin.layouts.app')

@section('title', 'Statistiques · ' . $shop->name)
@section('header', 'Statistiques boutiques')

@section('content')
@php
    $fcfa = fn ($v) => number_format((float) $v, 0, ',', ' ') . ' FCFA';
    $period = $summary['period'];
    $allTime = $summary['all_time'];
@endphp
<div class="p-6 space-y-6">
    <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-4">
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.statistics.index', ['period' => $period['key']]) }}"
               class="text-gray-400 hover:text-primary-500"><i class="fas fa-arrow-left"></i></a>
            <div>
                <h1 class="text-2xl font-bold text-white">{{ $shop->name }}</h1>
                <p class="text-gray-400">
                    {{ trim(($shop->user->first_name ?? '') . ' ' . ($shop->user->last_name ?? '')) }}
                    · {{ strtolower($period['label']) }}
                    · <a href="{{ route('admin.shops.show', $shop) }}" class="text-primary-400 hover:text-primary-300">fiche boutique</a>
                </p>
            </div>
        </div>
        @include('admin.statistics.partials.period-picker', [
            'route' => 'admin.statistics.shops.show',
            'routeParams' => ['shop' => $shop->id],
        ])
    </div>

    @include('admin.statistics.partials.kpis', [
        'totals' => $summary['totals'],
        'trends' => $summary['trends'],
        'showCommission' => true,
    ])

    @include('admin.statistics.partials.chart', [
        'series' => $summary['series'],
        'granularity' => $period['granularity'],
        'chartId' => 'shopStatsChart',
    ])

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        <!-- Produits les plus consultés -->
        <div class="xl:col-span-2 bg-dark-100 border border-dark-200 rounded-xl">
            <div class="p-6 border-b border-dark-200">
                <h3 class="text-lg font-semibold text-white">Produits les plus consultés</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-dark-50 text-gray-400 uppercase text-xs">
                        <tr>
                            <th class="px-4 py-3 text-left">Produit</th>
                            <th class="px-4 py-3 text-right">Consultations</th>
                            <th class="px-4 py-3 text-right">Vendus</th>
                            <th class="px-4 py-3 text-right">CA vendeur</th>
                            <th class="px-4 py-3 text-right">Conversion</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-dark-200 text-gray-200">
                        @forelse($summary['top_products'] as $product)
                            <tr>
                                <td class="px-4 py-3">
                                    <a href="{{ route('admin.products.show', $product['product_id']) }}" class="text-white hover:text-primary-400">
                                        {{ $product['name'] }}
                                    </a>
                                </td>
                                <td class="px-4 py-3 text-right">{{ number_format($product['views'], 0, ',', ' ') }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($product['items_sold'], 0, ',', ' ') }}</td>
                                <td class="px-4 py-3 text-right text-emerald-400">{{ $fcfa($product['revenue']) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($product['conversion_rate'], 1, ',', ' ') }} %</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-4 py-8 text-center text-gray-500">Aucune consultation ni vente sur la période.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Cumul depuis l'ouverture + gestion -->
        <div class="space-y-6">
            <div class="bg-dark-100 border border-dark-200 rounded-xl p-6">
                <h3 class="text-lg font-semibold text-white mb-4">Depuis l'ouverture</h3>
                <dl class="space-y-3 text-sm">
                    @foreach([
                        'Visites' => number_format($allTime['visits'], 0, ',', ' '),
                        'Visiteurs uniques' => number_format($allTime['unique_visitors'], 0, ',', ' '),
                        'Visites (7 derniers jours)' => number_format($allTime['visits_last_7_days'], 0, ',', ' '),
                        'Produits consultés' => number_format($allTime['product_views'], 0, ',', ' '),
                        'Contacts' => number_format($allTime['contacts'], 0, ',', ' '),
                        'Commandes' => number_format($allTime['orders'], 0, ',', ' '),
                        'Commandes en attente' => number_format($allTime['pending_orders'], 0, ',', ' '),
                        'Ventes validées' => number_format($allTime['sales_count'], 0, ',', ' '),
                        'Articles vendus' => number_format($allTime['items_sold'], 0, ',', ' '),
                        'Chiffre d\'affaires vendeur' => $fcfa($allTime['revenue']),
                    ] as $label => $value)
                        <div class="flex justify-between">
                            <dt class="text-gray-400">{{ $label }}</dt>
                            <dd class="font-semibold text-white">{{ $value }}</dd>
                        </div>
                    @endforeach
                </dl>
            </div>

            <div class="bg-dark-100 border border-dark-200 rounded-xl p-6">
                <h3 class="text-lg font-semibold text-white mb-2">Remettre l'audience à zéro</h3>
                <p class="text-sm text-gray-400 mb-4">
                    Supprime les visites, consultations et contacts enregistrés pour cette boutique
                    (par exemple après des tests). Les commandes, ventes et le chiffre d'affaires ne changent pas.
                </p>
                <form method="POST" action="{{ route('admin.statistics.shops.reset', $shop) }}"
                      data-confirm="Supprimer définitivement l'audience enregistrée de cette boutique ?">
                    @csrf
                    <button class="w-full px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg">
                        <i class="fas fa-eraser mr-2"></i>Remettre l'audience à zéro
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
