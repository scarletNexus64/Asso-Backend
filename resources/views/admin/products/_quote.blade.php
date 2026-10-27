<div class="bg-dark-100 rounded-xl shadow-lg p-6">
    <input type="hidden" name="requires_quote" value="0">
    <label class="flex items-start gap-3 text-white">
        <input type="checkbox" name="requires_quote" value="1"
               {{ old('requires_quote', $product->requires_quote ?? false) ? 'checked' : '' }} class="mt-1">
        <span>
            <span class="block font-medium">Prix et disponibilité à confirmer</span>
            <span class="block text-sm text-gray-400 mt-1">Le produit actif est visible dans le catalogue. ASSO doit confirmer l’offre avant qu’une commande puisse être payée. Décochez après validation du prix et de l’approvisionnement.</span>
        </span>
    </label>
</div>
