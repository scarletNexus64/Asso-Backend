@extends('admin.layouts.app')

@section('content')
<div class="p-6">
    <div class="mb-6 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-white">Références ASSO</h1>
            <p class="text-gray-400">Identifiant de chaque produit ASSO. Une référence placée sur la carte devient le point de départ de la livraison de ses produits.</p>
        </div>
        @adminCan('admin.product-references.create')
        <a href="{{ route('admin.product-references.create') }}"
           class="shrink-0 px-4 py-2 bg-gradient-to-r from-primary-500 to-primary-600 text-white rounded-lg hover:shadow-lg transition-all">
            <i class="fas fa-plus mr-2"></i>Nouvelle référence
        </a>
        @endadminCan
    </div>

    @if(session('success'))
        <div class="mb-6 rounded-lg border border-green-500/30 bg-green-500/10 px-4 py-3 text-green-300">
            <i class="fas fa-check-circle mr-2"></i>{{ session('success') }}
        </div>
    @endif

    {{-- Indicateurs --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        @foreach([
            ['Références', $stats['references'], 'fa-barcode', 'text-primary-400', null],
            ['Produits rattachés', $stats['linked'], 'fa-link', 'text-green-400', null],
            ['Produits ASSO sans référence', $stats['unlinked'], 'fa-unlink', 'text-yellow-400', route('admin.products.index', ['asso' => 1, 'asso_reference' => 'none'])],
            ['Références non géolocalisées', $stats['not_located'], 'fa-map-marker-alt', 'text-orange-400', route('admin.product-references.index', ['location' => 'shop'])],
        ] as [$label, $value, $icon, $tone, $link])
            <a @if($link) href="{{ $link }}" @endif class="block bg-dark-100 rounded-xl border border-dark-200 p-4 {{ $link ? 'hover:border-primary-500/50 transition-colors' : '' }}">
                <div class="flex items-center justify-between">
                    <p class="text-xs uppercase tracking-wider text-gray-500">{{ $label }}</p>
                    <i class="fas {{ $icon }} {{ $tone }}"></i>
                </div>
                <p class="mt-2 text-2xl font-bold text-white">{{ number_format($value, 0, ',', ' ') }}</p>
            </a>
        @endforeach
    </div>

    {{-- Filtres --}}
    <form method="GET" class="bg-dark-100 rounded-xl border border-dark-200 p-4 mb-6 grid grid-cols-1 md:grid-cols-4 gap-3">
        <input type="text" name="search" value="{{ request('search') }}" placeholder="Code, ville ou quartier…"
               class="md:col-span-2 px-4 py-2 bg-dark-50 border border-dark-300 text-white placeholder-gray-400 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
        <select name="status" class="px-4 py-2 bg-dark-50 border border-dark-300 text-white rounded-lg focus:ring-2 focus:ring-primary-500">
            <option value="">Tous les statuts</option>
            <option value="active" @selected(request('status') === 'active')>Actives</option>
            <option value="inactive" @selected(request('status') === 'inactive')>Désactivées</option>
        </select>
        <select name="location" class="px-4 py-2 bg-dark-50 border border-dark-300 text-white rounded-lg focus:ring-2 focus:ring-primary-500">
            <option value="">Toutes les localisations</option>
            <option value="located" @selected(request('location') === 'located')>Géolocalisées</option>
            <option value="shop" @selected(request('location') === 'shop')>Départ boutique</option>
        </select>
        <div class="md:col-span-4 flex gap-2 justify-end">
            @if(request()->hasAny(['search', 'status', 'location']))
                <a href="{{ route('admin.product-references.index') }}" class="px-4 py-2 bg-dark-200 text-gray-300 rounded-lg hover:bg-dark-50">Réinitialiser</a>
            @endif
            <button type="submit" class="px-4 py-2 bg-primary-500 text-white rounded-lg hover:bg-primary-600"><i class="fas fa-filter mr-2"></i>Filtrer</button>
        </div>
    </form>

    {{-- Liste --}}
    <div class="bg-dark-100 rounded-xl border border-dark-200 overflow-hidden">
        @if($references->isEmpty())
            <div class="p-10 text-center text-gray-400">
                <i class="fas fa-barcode text-3xl mb-3 text-gray-600"></i>
                <p>{{ request()->hasAny(['search', 'status', 'location']) ? 'Aucune référence ne correspond à ces filtres.' : 'Aucune référence enregistrée.' }}</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-dark-50 text-gray-400 text-xs uppercase tracking-wider">
                        <tr>
                            <th class="px-4 py-3 text-left">Référence</th>
                            <th class="px-4 py-3 text-left">Point de départ</th>
                            <th class="px-4 py-3 text-center">Produits</th>
                            <th class="px-4 py-3 text-center">Statut</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-dark-200">
                        @foreach($references as $reference)
                            <tr class="hover:bg-dark-50/50">
                                <td class="px-4 py-3">
                                    <span class="font-mono font-semibold text-white">{{ $reference->name }}</span>
                                </td>
                                <td class="px-4 py-3">
                                    @if($reference->isLocated())
                                        <span class="inline-flex items-center gap-1 rounded-full bg-green-500/10 px-2 py-0.5 text-xs text-green-300"><i class="fas fa-map-marker-alt"></i>Géolocalisée</span>
                                        <p class="mt-1 text-gray-300">{{ $reference->placeLabel() }}</p>
                                        <p class="text-xs text-gray-500 truncate max-w-xs" title="{{ $reference->address }}">{{ $reference->address }}</p>
                                    @else
                                        <span class="inline-flex items-center gap-1 rounded-full bg-gray-500/10 px-2 py-0.5 text-xs text-gray-300"><i class="fas fa-store"></i>Départ boutique</span>
                                        @if($reference->placeLabel())<p class="mt-1 text-gray-400">{{ $reference->placeLabel() }}</p>@endif
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-center">
                                    @if($reference->products_count)
                                        <a href="{{ route('admin.products.index', ['asso' => 1, 'asso_reference' => $reference->id]) }}"
                                           class="inline-flex items-center gap-1 rounded-lg bg-primary-500/10 px-3 py-1 text-primary-300 hover:bg-primary-500/20">
                                            <i class="fas fa-box"></i>{{ $reference->products_count }}
                                        </a>
                                    @else
                                        <span class="text-gray-500">0</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-center">
                                    @if($reference->is_active)
                                        <span class="rounded-full bg-green-500/10 px-2 py-0.5 text-xs text-green-300">Active</span>
                                    @else
                                        <span class="rounded-full bg-red-500/10 px-2 py-0.5 text-xs text-red-300">Désactivée</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right">
                                    @adminCan('admin.product-references.edit')
                                    <a href="{{ route('admin.product-references.edit', $reference) }}" class="text-primary-400 hover:text-primary-300" title="Modifier">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    @endadminCan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
@endsection
