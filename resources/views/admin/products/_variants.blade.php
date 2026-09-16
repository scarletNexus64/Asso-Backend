@php
    $variantRows = old('variants');
    if ($variantRows === null && isset($product)) {
        $variantRows = $product->variants->map(fn ($variant) => [
            'attributes' => collect($variant->attributes)->map(fn ($value, $name) => "$name: $value")->implode('; '),
            'sku' => $variant->sku,
            'price_adjustment' => $variant->price_adjustment,
            'stock' => $variant->stock,
            'is_active' => $variant->is_active ? 1 : 0,
        ])->all();
    }
    $variantRows ??= [];
@endphp
<div class="bg-dark-100 rounded-xl shadow-lg p-6">
    <div class="flex items-center justify-between mb-4">
        <div><h3 class="text-lg font-bold text-white"><i class="fas fa-layer-group text-primary-500 mr-2"></i>Choix proposés au client</h3><p class="mt-1 text-sm text-gray-400">Ajoutez simplement les couleurs, tailles ou pointures disponibles. La quantité totale sera calculée automatiquement.</p></div>
        <button type="button" onclick="addVariantRow()" class="px-3 py-2 bg-primary-600 text-white text-sm rounded-lg"><i class="fas fa-plus mr-1"></i> Ajouter un choix</button>
    </div>
    <div id="variants_wrapper" class="space-y-3"></div>
    <p id="variants_empty" class="text-sm text-gray-500">Ce produit n'a pas encore de choix particuliers. La quantité générale sera utilisée.</p>
    @error('variants.*.attributes')<p class="mt-2 text-sm text-red-400">{{ $message }}</p>@enderror
</div>
@push('scripts')
<script>
let productVariantIndex = 0;
function addVariantRow(data = {}) {
    const index = productVariantIndex++;
    const esc = value => String(value ?? '').replace(/[&<>"]/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[char]));
    const rawAttributes = String(data.attributes ?? '');
    const separator = rawAttributes.indexOf(':');
    const optionType = separator >= 0 ? rawAttributes.slice(0, separator).trim() : '';
    const optionValue = separator >= 0 ? rawAttributes.slice(separator + 1).trim() : rawAttributes;
    document.getElementById('variants_wrapper').insertAdjacentHTML('beforeend', `
    <div class="variant-row bg-dark-50 border border-dark-300 rounded-lg p-4">
      <div class="grid grid-cols-1 md:grid-cols-12 gap-3">
        <input type="hidden" name="variants[${index}][attributes]" value="${esc(rawAttributes)}" data-variant-attributes>
        <div class="md:col-span-4"><label class="block text-sm text-gray-300 mb-1">Type de choix *</label><input value="${esc(optionType)}" oninput="syncSimpleVariant(this)" data-option-type required placeholder="Ex : Couleur, Taille, Pointure" class="w-full px-3 py-2 bg-dark-100 border border-dark-300 rounded text-white"></div>
        <div class="md:col-span-4"><label class="block text-sm text-gray-300 mb-1">Choix proposé *</label><input value="${esc(optionValue)}" oninput="syncSimpleVariant(this)" data-option-value required placeholder="Ex : Rouge, XL, 42" class="w-full px-3 py-2 bg-dark-100 border border-dark-300 rounded text-white"></div>
        <div class="md:col-span-3"><label class="block text-sm text-gray-300 mb-1">Quantité disponible *</label><input type="number" min="0" name="variants[${index}][stock]" value="${esc(data.stock ?? 0)}" required class="w-full px-3 py-2 bg-dark-100 border border-dark-300 rounded text-white"></div>
        <div class="md:col-span-1 flex items-end"><button type="button" title="Retirer ce choix" onclick="this.closest('.variant-row').remove(); syncVariantState()" class="w-full px-3 py-2 bg-red-600 text-white rounded"><i class="fas fa-trash"></i></button></div>
      </div>
      <details class="mt-3"><summary class="cursor-pointer text-xs text-gray-400 hover:text-white">Options avancées (facultatif)</summary>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mt-3">
          <div><label class="block text-xs text-gray-400 mb-1">Référence interne</label><input name="variants[${index}][sku]" value="${esc(data.sku)}" placeholder="Facultatif" class="w-full px-3 py-2 bg-dark-100 border border-dark-300 rounded text-white text-sm"></div>
          <div><label class="block text-xs text-gray-400 mb-1">Supplément de prix</label><input type="number" step="0.01" name="variants[${index}][price_adjustment]" value="${esc(data.price_adjustment ?? 0)}" class="w-full px-3 py-2 bg-dark-100 border border-dark-300 rounded text-white text-sm"></div>
          <label class="flex items-center gap-2 pt-6 text-sm text-gray-300"><input type="checkbox" name="variants[${index}][is_active]" value="1" ${String(data.is_active ?? 1) !== '0' ? 'checked' : ''}> Montrer ce choix aux clients</label>
        </div>
      </details>
    </div>`);
    syncVariantState();
}
function syncSimpleVariant(input) {
    const row = input.closest('.variant-row');
    const type = row.querySelector('[data-option-type]').value.trim();
    const value = row.querySelector('[data-option-value]').value.trim();
    row.querySelector('[data-variant-attributes]').value = type && value ? `${type}: ${value}` : '';
}
function syncVariantState() {
    const hasVariants = document.querySelectorAll('.variant-row').length > 0;
    document.getElementById('variants_empty')?.classList.toggle('hidden', hasVariants);
    const stock = document.querySelector('input[name="stock"]');
    if (stock) { stock.readOnly = hasVariants; stock.title = hasVariants ? 'Calculé à partir des variantes' : ''; }
}
document.addEventListener('DOMContentLoaded', () => { @json($variantRows).forEach(row => addVariantRow(row)); syncVariantState(); });
</script>
@endpush
