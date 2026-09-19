@extends('admin.layouts.app')

@section('content')
<div class="p-6">
    <div class="mb-6 flex items-center gap-3">
        <a href="{{ route('admin.delivery-partners.index') }}" class="text-gray-400 hover:text-primary-500"><i class="fas fa-arrow-left"></i></a>
        <div>
            <h1 class="text-2xl font-bold text-white">{{ $partner->name }}</h1>
            <p class="text-gray-400">Conditions et grilles affichées à l'acheteur avant la commande.</p>
        </div>
    </div>

    <!-- Services proposés -->
    <div class="mb-6 flex flex-wrap gap-2 text-sm">
        <span class="text-gray-400 mr-1">Services proposés :</span>
        @foreach($partner->cityGrids as $grid)
            <span class="px-3 py-1 rounded-full bg-blue-900/30 text-blue-300 border border-blue-500/40">
                <i class="fas fa-city mr-1"></i> Urbain {{ $grid->city }} — zone à zone, {{ count($grid->vehicles) }} véhicules{{ $grid->is_active ? '' : ' (inactif)' }}
            </span>
        @endforeach
        @if($partner->deliveryZones()->exists())
            <span class="px-3 py-1 rounded-full bg-blue-900/30 text-blue-300 border border-blue-500/40">
                <i class="fas fa-map-marked-alt mr-1"></i> Urbain — {{ $partner->deliveryZones()->count() }} zone(s) sur carte
            </span>
        @endif
        @if($partner->deliveryRoutes->isNotEmpty())
            <span class="px-3 py-1 rounded-full bg-purple-900/30 text-purple-300 border border-purple-500/40">
                <i class="fas fa-route mr-1"></i> {{ $partner->deliveryRoutes->where('origin_country', '!=', 'CM')->isNotEmpty() ? 'International' : 'Interurbain' }} — {{ $partner->deliveryRoutes->count() }} trajet(s)
            </span>
        @endif
        @if($partner->cityGrids->isEmpty() && !$partner->deliveryZones()->exists() && $partner->deliveryRoutes->isEmpty())
            <span class="text-yellow-400">Aucun service configuré : ajoutez des trajets ci-dessous.</span>
        @endif
    </div>

    @if($errors->any())
        <div class="mb-6 p-4 bg-red-900/20 border-l-4 border-red-500 rounded">
            <ul class="text-red-300 text-sm list-disc list-inside">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <!-- Partenaire -->
    <div class="bg-dark-100 rounded-xl shadow-lg border border-dark-200 p-6 mb-6">
        <h2 class="text-lg font-semibold text-white mb-4"><i class="fas fa-building mr-2 text-primary-400"></i>Partenaire</h2>
        <form action="{{ route('admin.delivery-partners.update', $partner) }}" method="POST">
            @csrf
            @method('PUT')
            @include('admin.delivery_partners._partner_fields', ['partner' => $partner])
            <div class="mt-4 flex items-center justify-between">
                <label class="flex items-center gap-2 text-sm text-gray-300">
                    <input type="checkbox" name="is_active" value="1" @checked($partner->is_active) class="w-4 h-4 rounded bg-dark-50">
                    Partenaire actif (proposé aux acheteurs)
                </label>
                <button type="submit" class="px-4 py-2 bg-primary-500 text-white rounded-lg hover:bg-primary-600">
                    <i class="fas fa-save mr-2"></i> Enregistrer
                </button>
            </div>
        </form>
    </div>

    <!-- Grilles urbaines zone à zone -->
    @foreach($partner->cityGrids as $grid)
        <div class="bg-dark-100 rounded-xl shadow-lg border border-dark-200 p-6 mb-6">
            <h2 class="text-lg font-semibold text-white mb-1"><i class="fas fa-city mr-2 text-primary-400"></i>Livraison urbaine à {{ $grid->city }} (zone à zone)</h2>
            <p class="text-sm text-gray-400 mb-4">Une offre par véhicule capable de porter le colis, avec son délai estimé. Livrée à domicile par un coursier {{ $partner->name }} synchronisé.</p>
            @include('admin.delivery_partners._city_grid', ['grid' => $grid])
        </div>
    @endforeach

    <!-- Trajets -->
    <div class="bg-dark-100 rounded-xl shadow-lg border border-dark-200 p-6 mb-6">
        <h2 class="text-lg font-semibold text-white mb-1"><i class="fas fa-route mr-2 text-primary-400"></i>Trajets interurbains et internationaux</h2>
        <p class="text-sm text-gray-400 mb-4">
            Poids du colis = somme des poids des fiches produit × quantités. Au-delà de la dernière tranche, chaque kg entamé est facturé au prix du kg supplémentaire.
            @if($partner->prices_exclude_vat)
                Grille hors taxe : l'acheteur paie en plus {{ rtrim(rtrim(number_format($vatRate, 2, ',', ''), '0'), ',') }} % de TVA.
            @endif
            @if($partner->service_mode === 'agency_to_agency')
                L'acheteur choisit le retrait en agence, ou la livraison à domicile depuis l'agence d'arrivée (prix de la grille urbaine ajouté).
            @endif
        </p>

        @forelse($partner->deliveryRoutes as $route)
            <div x-data="{ open: false }" class="border border-dark-200 rounded-lg mb-3">
                <div class="flex items-center justify-between px-4 py-3">
                    <div class="text-sm">
                        <span class="text-white font-medium">{{ $route->label() }}</span>
                        <span class="text-gray-400 ml-2">{{ $route->lead_time }}</span>
                        @unless($route->is_active)<span class="ml-2 px-2 py-0.5 rounded text-xs bg-gray-800 text-gray-400">Inactif</span>@endunless
                        <div class="text-gray-400 mt-1">
                            @foreach(\App\Support\WeightGrid::describe($route->pricing_data ?? []) as $range)
                                {{ $range['label'] }} : <span class="text-gray-200">{{ number_format($range['price'], 0, ',', ' ') }} F</span> ·
                            @endforeach
                            @if(($route->pricing_data['extra_per_kg'] ?? 0) > 0)
                                kg suppl. : <span class="text-gray-200">{{ number_format($route->pricing_data['extra_per_kg'], 0, ',', ' ') }} F</span>
                            @endif
                        </div>
                    </div>
                    <div class="flex items-center gap-3 whitespace-nowrap">
                        <button type="button" @click="open = !open" class="text-primary-400 hover:text-primary-300 text-sm"><i class="fas fa-edit mr-1"></i> Modifier</button>
                        <form action="{{ route('admin.delivery-partners.routes.destroy', [$partner, $route]) }}" method="POST"
                              data-confirm="Supprimer le trajet {{ $route->label() }} ?">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-red-400 hover:text-red-300 text-sm"><i class="fas fa-trash"></i></button>
                        </form>
                    </div>
                </div>
                <div x-show="open" x-cloak class="px-4 pb-4 border-t border-dark-200 pt-4">
                    @include('admin.delivery_partners._route_form', [
                        'route' => $route,
                        'action' => route('admin.delivery-partners.routes.update', [$partner, $route]),
                        'method' => 'PUT',
                    ])
                </div>
            </div>
        @empty
            <p class="text-gray-500 text-sm mb-4">Aucun trajet : ce partenaire n'est proposé que pour ses zones urbaines.</p>
        @endforelse

        <div class="mt-6 p-4 bg-dark-50 rounded-lg border border-dashed border-dark-300">
            <h3 class="text-white font-medium mb-3"><i class="fas fa-plus-circle mr-1 text-primary-400"></i> Ajouter un trajet</h3>
            @include('admin.delivery_partners._route_form', [
                'route' => null,
                'action' => route('admin.delivery-partners.routes.store', $partner),
            ])
        </div>
    </div>
</div>
@endsection
