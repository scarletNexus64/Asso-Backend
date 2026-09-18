{{-- Cartes d'indicateurs. Attend $totals, $trends (nullable), $showCommission (bool). --}}
@php
    $fcfa = fn ($v) => number_format((float) $v, 0, ',', ' ') . ' FCFA';
    $cards = [
        ['key' => 'visits', 'label' => 'Visites boutique', 'icon' => 'fa-store', 'color' => 'blue', 'value' => number_format($totals['visits'], 0, ',', ' ')],
        ['key' => 'unique_visitors', 'label' => 'Visiteurs uniques', 'icon' => 'fa-user-friends', 'color' => 'indigo', 'value' => number_format($totals['unique_visitors'], 0, ',', ' ')],
        ['key' => 'product_views', 'label' => 'Produits consultés', 'icon' => 'fa-eye', 'color' => 'purple', 'value' => number_format($totals['product_views'], 0, ',', ' ')],
        ['key' => 'contacts', 'label' => 'Contacts vendeur', 'icon' => 'fa-comment-dots', 'color' => 'teal', 'value' => number_format($totals['contacts'], 0, ',', ' ')],
        ['key' => 'orders', 'label' => 'Commandes reçues', 'icon' => 'fa-shopping-cart', 'color' => 'amber', 'value' => number_format($totals['orders'], 0, ',', ' '), 'hint' => $totals['pending_orders'] . ' en attente · ' . $totals['cancelled_orders'] . ' annulée(s)'],
        ['key' => 'validated_orders', 'label' => 'Ventes validées', 'icon' => 'fa-check-circle', 'color' => 'green', 'value' => number_format($totals['validated_orders'], 0, ',', ' '), 'hint' => $totals['items_sold'] . ' article(s) vendu(s)'],
        ['key' => 'revenue', 'label' => 'Chiffre d\'affaires vendeur', 'icon' => 'fa-coins', 'color' => 'emerald', 'value' => $fcfa($totals['revenue']), 'hint' => 'Panier moyen : ' . $fcfa($totals['average_basket'])],
        ['key' => null, 'label' => 'Taux de conversion', 'icon' => 'fa-percentage', 'color' => 'rose', 'value' => number_format($totals['conversion_rate'], 1, ',', ' ') . ' %', 'hint' => 'Ventes / visiteurs uniques'],
    ];
    if (!empty($showCommission)) {
        $cards[] = ['key' => null, 'label' => 'Volume payé par les clients', 'icon' => 'fa-receipt', 'color' => 'sky', 'value' => $fcfa($totals['gross_sales'])];
        $cards[] = ['key' => null, 'label' => 'Commission ASSO', 'icon' => 'fa-hand-holding-usd', 'color' => 'orange', 'value' => $fcfa($totals['commission'])];
    }
@endphp
<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
    @foreach($cards as $card)
        @php $trend = $card['key'] && $trends ? ($trends[$card['key']] ?? null) : null; @endphp
        <div class="bg-dark-100 border border-dark-200 rounded-xl p-5">
            <div class="flex items-start justify-between">
                <div class="min-w-0">
                    <p class="text-sm text-gray-400">{{ $card['label'] }}</p>
                    <p class="text-2xl font-bold text-white mt-1 truncate">{{ $card['value'] }}</p>
                </div>
                <div class="p-3 rounded-lg bg-{{ $card['color'] }}-500/20 text-{{ $card['color'] }}-400">
                    <i class="fas {{ $card['icon'] }}"></i>
                </div>
            </div>
            <div class="mt-3 text-xs flex items-center gap-2">
                @if($card['key'] && $trends)
                    @if($trend === null)
                        <span class="text-green-400"><i class="fas fa-arrow-up"></i> nouveau</span>
                    @elseif($trend > 0)
                        <span class="text-green-400"><i class="fas fa-arrow-up"></i> {{ number_format($trend, 1, ',', ' ') }} %</span>
                    @elseif($trend < 0)
                        <span class="text-red-400"><i class="fas fa-arrow-down"></i> {{ number_format(abs($trend), 1, ',', ' ') }} %</span>
                    @else
                        <span class="text-gray-500">= stable</span>
                    @endif
                    <span class="text-gray-500">vs période précédente</span>
                @endif
                @if(!empty($card['hint']))
                    <span class="text-gray-500">{{ $card['hint'] }}</span>
                @endif
            </div>
        </div>
    @endforeach
</div>
