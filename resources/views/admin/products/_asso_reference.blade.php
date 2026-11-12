@php($currentReference = isset($product) ? $references->firstWhere('id', $product->asso_product_reference_id) : null)
<div id="asso-reference-field" class="mb-4" hidden>
    <label class="block text-sm font-medium text-white mb-2" for="asso_product_reference_id">Référence ASSO</label>
    <select id="asso_product_reference_id" name="asso_product_reference_id" class="w-full px-4 py-2 bg-dark-50 border border-dark-300 rounded-lg text-white font-mono">
        <option value="">Sélectionnez une référence</option>
        @foreach($references as $reference)
            <option value="{{ $reference->id }}" @selected(old('asso_product_reference_id', $product->asso_product_reference_id ?? null) == $reference->id)>{{ $reference->optionLabel() }}</option>
        @endforeach
    </select>
    @error('asso_product_reference_id')<p class="text-red-400 text-sm mt-1">{{ $message }}</p>@enderror
    <p class="text-gray-400 text-sm mt-1">
        @if($currentReference?->isLocated())
            Départ de la livraison : {{ $currentReference->placeLabel() }}.
        @else
            Tant que la référence n'est pas placée sur la carte, la livraison part de la boutique.
        @endif
        <a href="{{ route('admin.product-references.index') }}" class="text-primary-400 hover:text-primary-300" target="_blank">Gérer les références</a>
    </p>
</div>
<script>
document.addEventListener('DOMContentLoaded', () => {
    // Champ réservé aux produits des boutiques ASSO (produits de gros compris).
    const shops = @json($shops->where('user_id', \App\Support\AssoScope::ownerId())->pluck('id')->values());
    const shop = document.querySelector('select[name="shop_id"]');
    const field = document.getElementById('asso-reference-field');
    const input = document.getElementById('asso_product_reference_id');
    if (!shop || !field || !input) return;
    const refresh = () => {
        const visible = shops.includes(Number(shop.value));
        field.hidden = !visible;
        input.disabled = !visible;
    };
    shop.addEventListener('change', refresh);
    refresh();
});
</script>
