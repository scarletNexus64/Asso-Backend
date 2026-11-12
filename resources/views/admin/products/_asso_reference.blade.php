<div id="asso-reference-field" class="mb-4" hidden>
    <label class="block text-sm font-medium text-white mb-2" for="asso_product_reference_id">Référence ASSO</label>
    <select id="asso_product_reference_id" name="asso_product_reference_id" class="w-full px-4 py-2 bg-dark-50 border border-dark-300 rounded-lg text-white">
        <option value="">Sélectionnez une référence</option>
        @foreach($references as $reference)
            <option value="{{ $reference->id }}" @selected(old('asso_product_reference_id', $product->asso_product_reference_id ?? null) == $reference->id)>{{ $reference->name }} — {{ $reference->city }}{{ $reference->is_active ? '' : ' (désactivée)' }}</option>
        @endforeach
    </select>
    @error('asso_product_reference_id')<p class="text-red-400">{{ $message }}</p>@enderror
    <p class="text-gray-400 text-sm mt-1">La localisation de la référence sert de point de départ pour la livraison.</p>
</div>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const shops = @json($shops->where('user_id', \App\Support\AssoScope::ownerId())->pluck('id')->values());
    const shop = document.querySelector('select[name="shop_id"]');
    const field = document.getElementById('asso-reference-field');
    const input = document.getElementById('asso_product_reference_id');
    const refresh = () => {
        const wholesale = document.getElementById('is_wholesale')?.checked;
        const visible = shops.includes(Number(shop.value)) && !wholesale;
        field.hidden = !visible;
        input.disabled = !visible;
    };
    shop.addEventListener('change', refresh);
    document.getElementById('is_wholesale')?.addEventListener('change', refresh);
    refresh();
});
</script>
