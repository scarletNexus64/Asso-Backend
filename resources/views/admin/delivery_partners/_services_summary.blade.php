{{-- Services réels d'un partenaire, identiques dans « Livreurs » et « Partenaires logistiques ». --}}
@php
    $grids = $partner->cityGrids;
    $routes = $partner->deliveryRoutes;
    $mapZones = $partner->deliveryZones;
    $compact = $compact ?? false;
@endphp
<div class="flex flex-wrap gap-2 text-sm">
    @foreach($grids as $grid)
        <span class="px-3 py-1 rounded-full bg-blue-900/30 text-blue-300 border border-blue-500/40">
            <i class="fas fa-city mr-1"></i> Urbain {{ $grid->city }} — {{ count($grid->zones) }} zones, {{ count($grid->vehicles) }} véhicules{{ $grid->is_active ? '' : ' (inactif)' }}
        </span>
    @endforeach
    @if($mapZones->isNotEmpty())
        <span class="px-3 py-1 rounded-full bg-blue-900/30 text-blue-300 border border-blue-500/40">
            <i class="fas fa-map-marked-alt mr-1"></i> Urbain — {{ $mapZones->count() }} zone(s) sur carte
        </span>
    @endif
    @if($routes->isNotEmpty())
        <span class="px-3 py-1 rounded-full bg-purple-900/30 text-purple-300 border border-purple-500/40">
            <i class="fas fa-route mr-1"></i> {{ $routes->contains(fn ($r) => $r->origin_country !== $r->destination_country) ? 'International' : 'Interurbain' }} — {{ $routes->count() }} trajet(s)
        </span>
    @endif
    @if($grids->isEmpty() && $mapZones->isEmpty() && $routes->isEmpty())
        <span class="px-3 py-1 rounded-full bg-yellow-900/30 text-yellow-300 border border-yellow-500/40">
            <i class="fas fa-exclamation-triangle mr-1"></i> Aucun service configuré
        </span>
    @endif
</div>

@unless($compact)
    <div class="mt-4 space-y-3 text-sm">
        @foreach($grids as $grid)
            <div class="p-3 bg-dark-50 rounded-lg border border-dark-300">
                <div class="text-white font-medium mb-1"><i class="fas fa-city text-primary-500 mr-1"></i> {{ $grid->city }} — livraison à domicile, prix zone à zone</div>
                <div class="text-gray-400">
                    @foreach($grid->zones as $zone)
                        <div><span class="text-gray-300">{{ $zone['label'] }} :</span> {{ implode(', ', $zone['quarters'] ?? []) }}</div>
                    @endforeach
                </div>
                <div class="mt-2 text-gray-400">
                    Véhicules :
                    @foreach($grid->vehicles as $vehicle)
                        <span class="text-gray-200">{{ $vehicle['label'] }}</span>@if(!empty($vehicle['max_weight_kg'])) (≤ {{ rtrim(rtrim(number_format($vehicle['max_weight_kg'], 1, ',', ''), '0'), ',') }} kg)@endif{{ !empty($vehicle['lead_time']) ? ', ' . $vehicle['lead_time'] : '' }}{{ $loop->last ? '' : ' · ' }}
                    @endforeach
                </div>
                @unless($grid->agency_zone)
                    <p class="mt-2 text-yellow-400 text-xs"><i class="fas fa-exclamation-triangle mr-1"></i> Zone de l'agence à {{ $grid->city }} non renseignée : pas de livraison à domicile après un trajet interurbain arrivant ici.</p>
                @endunless
            </div>
        @endforeach
        @if($routes->isNotEmpty())
            <div class="p-3 bg-dark-50 rounded-lg border border-dark-300">
                <div class="text-white font-medium mb-1"><i class="fas fa-route text-primary-500 mr-1"></i> Trajets ({{ $partner->service_mode === 'agency_to_agency' ? "d'agence en agence, retrait ou livraison à domicile" : 'à domicile' }})</div>
                <div class="text-gray-400">
                    @foreach($routes as $route)
                        <span class="text-gray-200">{{ $route->label() }}</span>{{ $route->lead_time ? ' (' . $route->lead_time . ')' : '' }}{{ $loop->last ? '' : ' · ' }}
                    @endforeach
                </div>
            </div>
        @endif
        <a href="{{ route('admin.delivery-partners.edit', $partner) }}" class="inline-flex items-center text-primary-400 hover:text-primary-300">
            <i class="fas fa-tags mr-1"></i> Gérer les tarifs, zones et trajets
        </a>
    </div>
@endunless
