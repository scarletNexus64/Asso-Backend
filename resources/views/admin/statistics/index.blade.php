@extends('admin.layouts.app')

@section('title', 'Statistiques boutiques')
@section('header', 'Statistiques boutiques')

@section('content')
@php
    $fcfa = fn ($v) => number_format((float) $v, 0, ',', ' ') . ' FCFA';
    $period = $summary['period'];
@endphp
<div class="p-6 space-y-6">
    <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-4">
        <div>
            <h1 class="text-3xl font-bold text-gray-100">Statistiques boutiques</h1>
            <p class="text-gray-400 mt-1">
                Visites, produits consultés, commandes, ventes et chiffre d'affaires de toutes les boutiques
                — {{ strtolower($period['label']) }}.
            </p>
        </div>
        @include('admin.statistics.partials.period-picker', [
            'route' => 'admin.statistics.index',
            'extra' => array_filter(['sort' => $sort, 'search' => $search]),
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
        'chartId' => 'platformStatsChart',
    ])

    <!-- Classement des boutiques -->
    <div class="bg-dark-100 border border-dark-200 rounded-xl">
        <div class="p-6 border-b border-dark-200 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <h3 class="text-lg font-semibold text-white">Classement des boutiques</h3>
            <form method="GET" action="{{ route('admin.statistics.index') }}" class="flex flex-wrap gap-2">
                <input type="hidden" name="period" value="{{ $period['key'] }}">
                <input type="text" name="search" value="{{ $search }}" placeholder="Rechercher une boutique"
                       class="px-3 py-2 bg-dark-50 border border-dark-200 rounded-lg text-gray-100 text-sm">
                <select name="sort" class="px-3 py-2 bg-dark-50 border border-dark-200 rounded-lg text-gray-100 text-sm">
                    @foreach($sortable as $key => $label)
                        <option value="{{ $key }}" @selected($sort === $key)>Trier : {{ $label }}</option>
                    @endforeach
                </select>
                <button class="px-4 py-2 bg-primary-600 hover:bg-primary-700 text-white rounded-lg text-sm">Appliquer</button>
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-dark-50 text-gray-400 uppercase text-xs">
                    <tr>
                        <th class="px-4 py-3 text-left">Boutique</th>
                        <th class="px-4 py-3 text-right">Visites</th>
                        <th class="px-4 py-3 text-right">Visiteurs</th>
                        <th class="px-4 py-3 text-right">Produits consultés</th>
                        <th class="px-4 py-3 text-right">Contacts</th>
                        <th class="px-4 py-3 text-right">Commandes</th>
                        <th class="px-4 py-3 text-right">Articles vendus</th>
                        <th class="px-4 py-3 text-right">CA vendeur</th>
                        <th class="px-4 py-3 text-right">Conversion</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-dark-200 text-gray-200">
                    @forelse($shops as $shop)
                        @php $s = $shop->stats; @endphp
                        <tr class="hover:bg-dark-50/50">
                            <td class="px-4 py-3">
                                <div class="font-medium text-white">{{ $shop->name }}</div>
                                <div class="text-xs text-gray-500">
                                    {{ trim(($shop->user->first_name ?? '') . ' ' . ($shop->user->last_name ?? '')) ?: '—' }}
                                    · {{ $shop->status }}
                                </div>
                            </td>
                            <td class="px-4 py-3 text-right">{{ number_format($s['visits'] ?? 0, 0, ',', ' ') }}</td>
                            <td class="px-4 py-3 text-right">{{ number_format($s['unique_visitors'] ?? 0, 0, ',', ' ') }}</td>
                            <td class="px-4 py-3 text-right">{{ number_format($s['product_views'] ?? 0, 0, ',', ' ') }}</td>
                            <td class="px-4 py-3 text-right">{{ number_format($s['contacts'] ?? 0, 0, ',', ' ') }}</td>
                            <td class="px-4 py-3 text-right">
                                {{ number_format($s['orders'] ?? 0, 0, ',', ' ') }}
                                @if(($s['pending_orders'] ?? 0) > 0)
                                    <span class="text-xs text-yellow-400">({{ $s['pending_orders'] }} en attente)</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right">{{ number_format($s['items_sold'] ?? 0, 0, ',', ' ') }}</td>
                            <td class="px-4 py-3 text-right font-semibold text-emerald-400">{{ $fcfa($s['revenue'] ?? 0) }}</td>
                            <td class="px-4 py-3 text-right">{{ number_format($s['conversion_rate'] ?? 0, 1, ',', ' ') }} %</td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <a href="{{ route('admin.statistics.shops.show', ['shop' => $shop->id, 'period' => $period['key']]) }}"
                                   class="text-primary-400 hover:text-primary-300">
                                    <i class="fas fa-chart-bar mr-1"></i>Détails
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="px-4 py-10 text-center text-gray-500">Aucune boutique trouvée.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($shops->hasPages())
            <div class="p-4 border-t border-dark-200">{{ $shops->links() }}</div>
        @endif
    </div>

    <!-- Réglages de la collecte -->
    <div class="bg-dark-100 border border-dark-200 rounded-xl p-6">
        <h3 class="text-lg font-semibold text-white mb-1">Réglages de la collecte</h3>
        <p class="text-sm text-gray-400 mb-4">
            Une visite est comptée quand un acheteur ouvre une boutique ; une consultation quand il ouvre la fiche d'un produit ;
            un contact quand il écrit au vendeur. Les visites du vendeur sur sa propre boutique ne sont jamais comptées.
        </p>
        <form method="POST" action="{{ route('admin.statistics.settings') }}" class="flex flex-wrap items-end gap-6">
            @csrf
            <label class="flex items-center gap-3 text-gray-200">
                <input type="hidden" name="analytics_tracking_enabled" value="0">
                <input type="checkbox" name="analytics_tracking_enabled" value="1" @checked($trackingEnabled)
                       class="h-5 w-5 rounded border-dark-200 bg-dark-50 text-primary-600">
                Collecte activée
            </label>
            <div>
                <label for="analytics_dedup_minutes" class="block text-sm text-gray-300 mb-1">
                    Un même visiteur est compté une fois toutes les (minutes)
                </label>
                <input id="analytics_dedup_minutes" type="number" min="0" max="1440" name="analytics_dedup_minutes"
                       value="{{ $dedupMinutes }}"
                       class="w-40 px-3 py-2 bg-dark-50 border border-dark-200 rounded-lg text-gray-100">
                <p class="text-xs text-gray-500 mt-1">0 = chaque ouverture est comptée.</p>
            </div>
            <button class="px-4 py-2 bg-primary-600 hover:bg-primary-700 text-white rounded-lg">Enregistrer</button>
        </form>
    </div>
</div>
@endsection
