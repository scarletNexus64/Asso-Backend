@extends('admin.layouts.app')

@php
    use App\Models\PayoutAccount;
    $f = $filters;
@endphp

@section('title', 'Comptes de retrait')
@section('header', 'Comptes de retrait')

@section('content')
<div class="p-6 space-y-6">
    <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-white">Comptes de retrait</h1>
            <p class="text-gray-400 mt-1 max-w-3xl">
                Vendeurs et acheteurs ayant enregistré un numéro Mobile Money, un IBAN validé par Stripe (virement automatique)
                ou un IBAN manuel (banque non prise en charge par Stripe : virement fait par ASSO).
            </p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('admin.payouts.requests') }}" class="px-3 py-2 text-sm rounded-lg bg-dark-200 hover:bg-dark-300 text-gray-200"><i class="fas fa-hand-holding-dollar mr-1"></i>Demandes de retrait</a>
            <a href="{{ route('admin.stripe.accounts.index') }}" class="px-3 py-2 text-sm rounded-lg bg-dark-200 hover:bg-dark-300 text-gray-200"><i class="fas fa-university mr-1"></i>Validation Stripe</a>
        </div>
    </div>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        @foreach([
            ['Numéros Mobile Money', $stats['mobile'], 'fa-mobile-alt', ['mobile' => 'yes']],
            ['IBAN Stripe validés', $stats['stripe'], 'fa-check-circle', ['iban' => 'stripe']],
            ['IBAN manuels', $stats['manual_iban'], 'fa-university', ['iban' => 'manual']],
            ['Mobile + IBAN', $stats['both'], 'fa-layer-group', ['mobile' => 'yes', 'iban' => 'any']],
        ] as [$label, $value, $icon, $link])
            <a href="{{ route('admin.payouts.accounts', $link) }}" class="bg-dark-100 rounded-xl p-4 hover:bg-dark-50 transition">
                <p class="text-xs text-gray-400"><i class="fas {{ $icon }} mr-1"></i>{{ $label }}</p>
                <p class="text-2xl font-bold text-white mt-1">{{ $value }}</p>
            </a>
        @endforeach
    </div>

    <form method="GET" action="{{ route('admin.payouts.accounts') }}" class="bg-dark-100 rounded-xl shadow-lg p-4 grid grid-cols-1 md:grid-cols-5 gap-3">
        <select name="mobile" class="px-3 py-2 rounded-lg bg-dark-50 border border-dark-200 text-gray-200 text-sm">
            <option value="">Mobile Money : tous</option>
            <option value="yes" @selected($f['mobile'] === 'yes')>Avec numéro Mobile Money</option>
            <option value="no" @selected($f['mobile'] === 'no')>Sans numéro Mobile Money</option>
        </select>
        <select name="iban" class="px-3 py-2 rounded-lg bg-dark-50 border border-dark-200 text-gray-200 text-sm">
            <option value="">IBAN : tous</option>
            <option value="any" @selected($f['iban'] === 'any')>Avec un IBAN utilisable</option>
            <option value="stripe" @selected($f['iban'] === 'stripe')>IBAN Stripe validé (automatique)</option>
            <option value="stripe_pending" @selected($f['iban'] === 'stripe_pending')>IBAN Stripe en attente / rejeté</option>
            <option value="manual" @selected($f['iban'] === 'manual')>IBAN manuel (≈ 48 h)</option>
            <option value="none" @selected($f['iban'] === 'none')>Sans IBAN utilisable</option>
        </select>
        <select name="role" class="px-3 py-2 rounded-lg bg-dark-50 border border-dark-200 text-gray-200 text-sm">
            <option value="">Vendeurs et acheteurs</option>
            <option value="vendor" @selected($f['role'] === 'vendor')>Vendeurs</option>
            <option value="buyer" @selected($f['role'] === 'buyer')>Acheteurs</option>
        </select>
        <input type="search" name="q" value="{{ $f['search'] }}" placeholder="Nom, e-mail, téléphone…"
               class="px-3 py-2 rounded-lg bg-dark-50 border border-dark-200 text-gray-200 text-sm">
        <div class="flex gap-2">
            <button class="flex-1 px-4 py-2 rounded-lg bg-primary-600 hover:bg-primary-700 text-white text-sm font-medium"><i class="fas fa-filter mr-1"></i>Filtrer</button>
            <a href="{{ route('admin.payouts.accounts') }}" class="px-3 py-2 rounded-lg bg-dark-50 text-gray-300 hover:bg-dark-200 text-sm">Effacer</a>
        </div>
    </form>

    @if($users->isEmpty())
        <div class="bg-dark-100 rounded-xl shadow-lg p-12 text-center">
            <i class="fas fa-address-card text-6xl text-gray-600 mb-4"></i>
            <h3 class="text-xl font-semibold text-white mb-2">Aucun compte</h3>
            <p class="text-gray-400">Aucun compte de retrait ne correspond à ces filtres.</p>
        </div>
    @else
        <div class="bg-dark-100 rounded-xl shadow-lg overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="text-left text-xs uppercase text-gray-500 border-b border-dark-50">
                    <tr>
                        <th class="px-4 py-3">Utilisateur</th>
                        <th class="px-4 py-3">Mobile Money</th>
                        <th class="px-4 py-3">IBAN</th>
                        <th class="px-4 py-3 text-right">Disponible (carte / Mobile Money)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-dark-50">
                    @foreach($users as $u)
                        @php
                            $mobileAcc = $u->payoutAccounts->firstWhere('type', PayoutAccount::TYPE_MOBILE_MONEY);
                            $bankAcc = $u->payoutAccounts->firstWhere('type', PayoutAccount::TYPE_BANK_TRANSFER);
                            $xaf = $u->walletBalances->firstWhere('currency', 'XAF');
                        @endphp
                        <tr class="align-top">
                            <td class="px-4 py-3">
                                <p class="text-white font-medium">{{ trim($u->first_name . ' ' . $u->last_name) ?: 'Utilisateur #' . $u->id }}
                                    <span class="ml-1 text-xs px-2 py-0.5 rounded-full {{ $u->shops_exists ? 'bg-primary-500/20 text-primary-300' : 'bg-gray-500/20 text-gray-300' }}">{{ $u->shops_exists ? 'Vendeur' : 'Acheteur' }}</span>
                                </p>
                                <p class="text-gray-500 text-xs">{{ $u->email }} {{ $u->phone ? '· ' . $u->phone : '' }}</p>
                            </td>
                            <td class="px-4 py-3">
                                @if($mobileAcc)
                                    <p class="text-gray-200 font-mono">{{ $isAdmin ? $mobileAcc->phone_number : $mobileAcc->masked_phone }}</p>
                                    <p class="text-xs text-gray-500">{{ $mobileAcc->provider }} · {{ $mobileAcc->currency }} · {{ $mobileAcc->updated_at?->displayTz()->format('d/m/Y') }}</p>
                                @else
                                    <span class="text-gray-600">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 space-y-2">
                                @if($u->stripe_account_id)
                                    @php
                                        $st = $u->stripe_account_status;
                                        $cls = $st === 'approved' ? 'text-green-400' : ($st === 'rejected' ? 'text-red-400' : 'text-yellow-400');
                                    @endphp
                                    <div>
                                        <p class="text-gray-200">Stripe · •••• {{ $u->stripe_external_last4 ?: '????' }} <span class="text-gray-500">{{ $u->stripe_bank_country }}</span></p>
                                        <p class="text-xs {{ $cls }}">{{ ['approved' => 'Validé — virement automatique', 'rejected' => 'Rejeté', 'pending' => 'En attente de validation'][$st] ?? 'Inscription Stripe non terminée' }}</p>
                                    </div>
                                @endif
                                @if($bankAcc)
                                    <div>
                                        <p class="text-gray-200 font-mono">{{ $isAdmin ? \App\Services\IbanValidator::format((string) $bankAcc->iban) : $bankAcc->masked_iban }}</p>
                                        <p class="text-xs text-orange-300">IBAN manuel ({{ $bankAcc->country }}) — virement fait par ASSO</p>
                                        <p class="text-xs text-gray-500">{{ $bankAcc->account_holder }} @if($bankAcc->bic) · BIC {{ $bankAcc->bic }} @endif @if($bankAcc->bank_name) · {{ $bankAcc->bank_name }} @endif</p>
                                    </div>
                                @endif
                                @if(!$u->stripe_account_id && !$bankAcc)
                                    <span class="text-gray-600">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                @if($xaf)
                                    <p class="text-white font-semibold">{{ number_format(max(0, $xaf->available), 0, ',', ' ') }} FCFA</p>
                                    <p class="text-xs text-gray-500">carte {{ number_format($xaf->card_available, 0, ',', ' ') }} · mobile {{ number_format($xaf->mobile_available, 0, ',', ' ') }}</p>
                                @else
                                    <span class="text-gray-600">—</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div>{{ $users->links() }}</div>
    @endif
</div>
@endsection
