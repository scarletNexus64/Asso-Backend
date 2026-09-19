@extends('admin.layouts.app')

@section('content')
<div class="p-6" x-data="{ showCreate: {{ $errors->any() ? 'true' : 'false' }} }">
    <div class="mb-6 flex items-center justify-between flex-wrap gap-3">
        <div>
            <h1 class="text-2xl font-bold text-white">Partenaires logistiques</h1>
            <p class="text-gray-400">Tarifs au poids, zones desservies, TVA et conditions affichés à l'acheteur avant validation de la commande.</p>
        </div>
        <button type="button" @click="showCreate = !showCreate"
                class="px-4 py-2 bg-gradient-to-r from-primary-500 to-primary-600 text-white rounded-lg hover:shadow-lg transition-all">
            <i class="fas fa-plus mr-2"></i> Nouveau partenaire
        </button>
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

    <!-- Réglages globaux -->
    <div class="bg-dark-100 rounded-xl shadow-lg border border-dark-200 p-6 mb-6">
        <h2 class="text-lg font-semibold text-white mb-4"><i class="fas fa-sliders-h mr-2 text-primary-400"></i>Réglages de livraison</h2>
        <form action="{{ route('admin.delivery-partners.settings') }}" method="POST" class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
            @csrf
            @method('PUT')
            <div>
                <label class="block text-sm text-gray-400 mb-1">Rayon de couverture d'une zone urbaine (km)</label>
                <input type="number" name="delivery_zone_radius_km" step="0.5" min="0.5" max="500" required
                       value="{{ old('delivery_zone_radius_km', $radiusKm) }}"
                       class="w-full px-3 py-2 bg-dark-50 border border-dark-200 rounded-lg text-white focus:border-primary-500 focus:outline-none">
                <p class="mt-1 text-xs text-gray-500">Distance max. autour du centre d'une zone quand la ville de l'acheteur n'est pas reconnue. Sert aussi à l'installation des vendeurs.</p>
            </div>
            <div>
                <label class="block text-sm text-gray-400 mb-1">TVA sur les grilles hors taxe (%)</label>
                <input type="number" name="delivery_vat_rate" step="0.01" min="0" max="100" required
                       value="{{ old('delivery_vat_rate', $vatRate) }}"
                       class="w-full px-3 py-2 bg-dark-50 border border-dark-200 rounded-lg text-white focus:border-primary-500 focus:outline-none">
                <p class="mt-1 text-xs text-gray-500">Ajoutée au prix des partenaires dont la grille est « hors taxe » (ex. SOLEX).</p>
            </div>
            <div>
                <button type="submit" class="w-full px-4 py-2 bg-primary-500 text-white rounded-lg hover:bg-primary-600">
                    <i class="fas fa-save mr-2"></i> Enregistrer
                </button>
            </div>
        </form>
    </div>

    <!-- Création -->
    <div x-show="showCreate" x-cloak class="bg-dark-100 rounded-xl shadow-lg border border-primary-500/40 p-6 mb-6">
        <h2 class="text-lg font-semibold text-white mb-4"><i class="fas fa-truck mr-2 text-primary-400"></i>Nouveau partenaire</h2>
        <form action="{{ route('admin.delivery-partners.store') }}" method="POST">
            @csrf
            @include('admin.delivery_partners._partner_fields', ['partner' => null])
            <div class="mt-4 flex justify-end">
                <button type="submit" class="px-4 py-2 bg-primary-500 text-white rounded-lg hover:bg-primary-600">
                    <i class="fas fa-check mr-2"></i> Créer puis ajouter les trajets
                </button>
            </div>
        </form>
    </div>

    <!-- Liste -->
    <div class="bg-dark-100 rounded-xl shadow-lg border border-dark-200 overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-dark-200 text-gray-400 text-left">
                <tr>
                    <th class="px-4 py-3">Partenaire</th>
                    <th class="px-4 py-3">Catégorie</th>
                    <th class="px-4 py-3">Mode</th>
                    <th class="px-4 py-3 text-center">Zones urbaines</th>
                    <th class="px-4 py-3 text-center">Trajets</th>
                    <th class="px-4 py-3">TVA</th>
                    <th class="px-4 py-3">Statut</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-dark-200">
                @forelse($partners as $partner)
                    <tr class="text-gray-300">
                        <td class="px-4 py-3 text-white font-medium">{{ $partner->name }}</td>
                        <td class="px-4 py-3">{{ \App\Models\DelivererCompany::SERVICE_TYPES[$partner->service_type] ?? $partner->service_type }}</td>
                        <td class="px-4 py-3">{{ $partner->service_mode === 'agency_to_agency' ? "Agence → agence" : 'À domicile' }}</td>
                        <td class="px-4 py-3 text-center">{{ $partner->delivery_zones_count }}</td>
                        <td class="px-4 py-3 text-center">{{ $partner->delivery_routes_count }}</td>
                        <td class="px-4 py-3">{{ $partner->prices_exclude_vat ? 'Grille HT + TVA' : 'TTC' }}</td>
                        <td class="px-4 py-3">
                            @if($partner->is_active)
                                <span class="px-2 py-1 rounded text-xs bg-green-900/30 text-green-400">Actif</span>
                            @else
                                <span class="px-2 py-1 rounded text-xs bg-gray-800 text-gray-400">Inactif</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <a href="{{ route('admin.delivery-partners.edit', $partner) }}" class="text-primary-400 hover:text-primary-300 mr-3">
                                <i class="fas fa-route mr-1"></i> Conditions & trajets
                            </a>
                            <a href="{{ route('admin.deliverers.edit', $partner) }}" class="text-gray-400 hover:text-white">
                                <i class="fas fa-map-marked-alt mr-1"></i> Zones
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-4 py-6 text-center text-gray-500">Aucun partenaire.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
