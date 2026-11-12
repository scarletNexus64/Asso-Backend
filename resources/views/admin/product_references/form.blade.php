@extends('admin.layouts.app')

@section('content')
<div class="p-6 max-w-4xl">
    <div class="mb-6 flex items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-white">{{ $reference->exists ? 'Modifier la référence' : 'Nouvelle référence ASSO' }}</h1>
            @if($reference->exists)
                <p class="text-gray-400"><span class="font-mono">{{ $reference->name }}</span> · {{ $reference->products_count }} produit(s) rattaché(s)</p>
            @else
                <p class="text-gray-400">Le code est enregistré tel que saisi, en majuscules et sans espaces.</p>
            @endif
        </div>
        <a href="{{ route('admin.product-references.index') }}" class="shrink-0 px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700">
            <i class="fas fa-arrow-left mr-2"></i>Retour
        </a>
    </div>

    @if($errors->any())
        <div class="mb-6 rounded-lg border border-red-500/30 bg-red-500/10 px-4 py-3 text-red-300">
            @foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach
        </div>
    @endif

    <form method="POST" action="{{ $reference->exists ? route('admin.product-references.update', $reference) : route('admin.product-references.store') }}">
        @csrf
        @if($reference->exists) @method('PUT') @endif

        <div class="bg-dark-100 rounded-xl border border-dark-200 p-6 mb-6">
            <h2 class="text-lg font-semibold text-white mb-4"><i class="fas fa-barcode mr-2 text-primary-400"></i>Identification</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <label class="block">
                    <span class="block text-sm text-gray-300 mb-1">Code de la référence <span class="text-red-400">*</span></span>
                    <input id="reference_name" name="name" value="{{ old('name', $reference->name) }}" required maxlength="255"
                           placeholder="ASSO-FRN-XXX-001"
                           class="w-full px-4 py-2 bg-dark-50 border border-dark-300 rounded-lg text-white font-mono uppercase focus:ring-2 focus:ring-primary-500">
                    <span class="block text-xs text-gray-500 mt-1">Enregistré : <span id="reference_preview" class="font-mono text-gray-300"></span></span>
                </label>
                <label class="block">
                    <span class="block text-sm text-gray-300 mb-1">Statut</span>
                    @php($active = (int) old('is_active', $reference->exists ? (int) $reference->is_active : 1))
                    <select name="is_active" class="w-full px-4 py-2 bg-dark-50 border border-dark-300 rounded-lg text-white focus:ring-2 focus:ring-primary-500">
                        <option value="1" @selected($active === 1)>Active</option>
                        <option value="0" @selected($active === 0)>Désactivée</option>
                    </select>
                    <span class="block text-xs text-gray-500 mt-1">Une référence désactivée n'est plus proposée aux nouveaux produits.</span>
                </label>
            </div>
        </div>

        <div class="bg-dark-100 rounded-xl border border-dark-200 p-6 mb-6">
            <h2 class="text-lg font-semibold text-white mb-1"><i class="fas fa-map-marker-alt mr-2 text-orange-400"></i>Point de départ de la livraison</h2>
            <p class="text-sm text-gray-400 mb-4">Facultatif. Une fois la référence placée sur la carte (avec ville et pays), la livraison de ses produits part de ce lieu, produits de gros compris. Sans position, le départ reste la boutique ou l'entrepôt.</p>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                @foreach(['city' => 'Ville', 'country' => 'Pays', 'quarter' => 'Quartier'] as $field => $label)
                    <label class="block">
                        <span class="block text-sm text-gray-300 mb-1">{{ $label }}</span>
                        <input name="{{ $field }}" value="{{ old($field, $reference->$field) }}"
                               class="w-full px-4 py-2 bg-dark-50 border border-dark-300 rounded-lg text-white focus:ring-2 focus:ring-primary-500">
                    </label>
                @endforeach
            </div>
            @include('admin.partials.google-map', [
                'id' => 'reference-map',
                'label' => 'Position sur la carte',
                'required' => false,
                'wrapperClass' => 'bg-dark-50 border border-dark-300 rounded-lg p-4',
                'latitude' => old('latitude', $reference->latitude),
                'longitude' => old('longitude', $reference->longitude),
                'address' => old('address', $reference->address),
            ])
        </div>

        <div class="flex justify-end gap-3">
            <a href="{{ route('admin.product-references.index') }}" class="px-4 py-2 bg-dark-200 text-gray-300 rounded-lg hover:bg-dark-50">Annuler</a>
            <button type="submit" class="px-4 py-2 bg-gradient-to-r from-primary-500 to-primary-600 text-white rounded-lg hover:shadow-lg">
                <i class="fas fa-save mr-2"></i>Enregistrer
            </button>
        </div>
    </form>
</div>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const input = document.getElementById('reference_name');
    const preview = document.getElementById('reference_preview');
    // Même normalisation que le serveur : majuscules, sans espaces ni caractères invisibles.
    const refresh = () => { preview.textContent = input.value.replace(/[\s​-‍⁠﻿]+/g, '').replace(/[‐‑–—−]/g, '-').toUpperCase(); };
    input.addEventListener('input', refresh);
    refresh();
});
</script>
@endsection
