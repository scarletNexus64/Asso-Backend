@php
    $input = 'w-full px-2 py-1.5 bg-dark-50 border border-dark-200 rounded text-white text-sm focus:border-primary-500 focus:outline-none';
    $codes = collect($grid->zones)->pluck('code')->all();
@endphp
<form action="{{ route('admin.delivery-partners.city-grids.update', [$partner, $grid]) }}" method="POST" x-data="{ tab: '{{ $grid->vehicles[0]['code'] ?? '' }}' }">
    @csrf
    @method('PUT')

    <!-- Zones et quartiers -->
    <h3 class="text-white font-medium mb-2">Zones de couverture et quartiers</h3>
    <p class="text-xs text-gray-500 mb-3">L'acheteur choisit son quartier ; la boutique est placée dans sa zone d'après son adresse. Séparez les quartiers par des virgules.</p>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mb-6">
        @foreach($grid->zones as $zone)
            <div>
                <label class="block text-xs text-gray-400 mb-1">{{ $zone['label'] }}</label>
                <textarea name="zones[{{ $zone['code'] }}][quarters]" rows="2" class="{{ $input }}">{{ implode(', ', $zone['quarters'] ?? []) }}</textarea>
            </div>
        @endforeach
    </div>

    <!-- Véhicules -->
    <h3 class="text-white font-medium mb-2">Véhicules et prix zone à zone</h3>
    <p class="text-xs text-gray-500 mb-3">
        Prix {{ $partner->prices_exclude_vat ? 'hors taxe (la TVA est ajoutée)' : 'TTC' }}. Ligne = zone de départ, colonne = zone d'arrivée ;
        le prix vaut dans les deux sens. Le poids max. décide des véhicules proposés à l'acheteur.
    </p>
    <div class="flex flex-wrap gap-2 mb-3">
        @foreach($grid->vehicles as $vehicle)
            <button type="button" @click="tab = '{{ $vehicle['code'] }}'"
                    :class="tab === '{{ $vehicle['code'] }}' ? 'bg-primary-500 text-white' : 'bg-dark-50 text-gray-300'"
                    class="px-3 py-1.5 rounded-lg text-sm">{{ $vehicle['label'] }}</button>
        @endforeach
    </div>

    @foreach($grid->vehicles as $vehicle)
        @php $code = $vehicle['code']; @endphp
        <div x-show="tab === '{{ $code }}'" x-cloak class="mb-6">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-3">
                <div>
                    <label class="block text-xs text-gray-400 mb-1">Nom affiché</label>
                    <input type="text" name="vehicles[{{ $code }}][label]" value="{{ $vehicle['label'] }}" required class="{{ $input }}">
                </div>
                <div>
                    <label class="block text-xs text-gray-400 mb-1">Poids max. (kg)</label>
                    <input type="number" step="0.1" min="0.1" name="vehicles[{{ $code }}][max_weight_kg]" value="{{ $vehicle['max_weight_kg'] ?? '' }}" class="{{ $input }}">
                </div>
                <div>
                    <label class="block text-xs text-gray-400 mb-1">Délai estimé affiché</label>
                    <input type="text" name="vehicles[{{ $code }}][lead_time]" value="{{ $vehicle['lead_time'] ?? '' }}" placeholder="1 h à 3 h" class="{{ $input }}">
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="text-sm">
                    <thead>
                        <tr class="text-gray-400">
                            <th class="px-2 py-1 text-left">Départ \ Arrivée</th>
                            @foreach($codes as $to)
                                <th class="px-2 py-1">Zone {{ $to }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($codes as $from)
                            <tr>
                                <td class="px-2 py-1 text-gray-300 whitespace-nowrap">Zone {{ $from }}</td>
                                @foreach($codes as $to)
                                    <td class="px-1 py-1 min-w-[90px]">
                                        @if($to >= $from)
                                            <input type="number" step="1" min="0" name="vehicles[{{ $code }}][prices][{{ $from }}-{{ $to }}]"
                                                   value="{{ $vehicle['prices'][$from . '-' . $to] ?? '' }}" class="{{ $input }}">
                                        @else
                                            <span class="block text-center text-gray-600">=</span>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endforeach

    <div class="grid grid-cols-1 md:grid-cols-3 gap-3 items-end">
        <div>
            <label class="block text-xs text-gray-400 mb-1">Zone de l'agence {{ $partner->name }} à {{ $grid->city }}</label>
            <select name="agency_zone" class="{{ $input }}">
                <option value="">Non renseignée</option>
                @foreach($codes as $code)
                    <option value="{{ $code }}" @selected($grid->agency_zone === $code)>Zone {{ $code }}</option>
                @endforeach
            </select>
            <p class="mt-1 text-xs text-gray-500">Départ de la livraison à domicile après un trajet interurbain arrivant à {{ $grid->city }}.</p>
        </div>
        <div>
            <label class="block text-xs text-gray-400 mb-1">Commission ASSO (FCFA)</label>
            <input type="number" step="1" min="0" name="asso_commission" value="{{ $grid->asso_commission }}" class="{{ $input }}">
        </div>
        <div class="flex items-center justify-between gap-3">
            <label class="flex items-center gap-2 text-sm text-gray-300">
                <input type="checkbox" name="is_active" value="1" @checked($grid->is_active) class="w-4 h-4 rounded bg-dark-50">
                Grille active
            </label>
            <button type="submit" class="px-4 py-2 bg-primary-500 text-white rounded-lg hover:bg-primary-600 text-sm">
                <i class="fas fa-save mr-1"></i> Enregistrer
            </button>
        </div>
    </div>
</form>
