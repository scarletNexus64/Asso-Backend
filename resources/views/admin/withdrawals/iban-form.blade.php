{{-- IBAN du compte (plateforme ou partenaire) : saisi sur la page d'inscription hébergée par Stripe,
     seule méthode autorisée en France. Au retour, la page se met à jour (inscription terminée → « En attente »). --}}
@php
    $field = 'w-full bg-dark-200 border border-dark-300 rounded-lg px-3 py-2 text-gray-100 focus:outline-none focus:border-primary-500';
@endphp
<form method="POST" action="{{ $routes['iban'] }}" class="space-y-3">
    @csrf
    <p class="text-sm font-semibold text-gray-200"><i class="fas fa-sync mr-1"></i>Synchroniser l'IBAN</p>
    <p class="text-xs text-gray-400">
        L'identité et l'IBAN se saisissent sur la page sécurisée de Stripe (obligation réglementaire).
        Une fois l'inscription terminée, revenez sur cette page : l'IBAN apparaîtra « En attente » et pourra être validé.
    </p>
    @if(!empty($account->stripe_account_id))
        <p class="text-xs text-amber-300">Inscription Stripe commencée : le bouton ci-dessous permet de la reprendre.</p>
    @else
        <div class="sm:w-1/3">
            <label class="block text-sm text-gray-300 mb-1">Pays du compte bancaire</label>
            <input type="text" name="country" value="{{ old('country', $account->stripe_bank_country ?? 'FR') }}" maxlength="2" required class="{{ $field }} uppercase">
        </div>
    @endif
    <button type="submit" onclick="this.disabled=true; this.form.submit();"
            class="w-full px-4 py-2 rounded-lg bg-primary-600 hover:bg-primary-700 text-white font-semibold">
        <i class="fas fa-external-link-alt mr-1"></i>{{ empty($account->stripe_account_id) ? "Configurer l'IBAN sur Stripe" : "Reprendre l'inscription Stripe" }}
    </button>
</form>
