@extends('admin.layouts.app')

@php
    use App\Http\Controllers\Admin\WithdrawalRequestController as WRC;
    $money = fn ($amount, $currency) => number_format((float) $amount, in_array(strtoupper((string) $currency), ['XAF', 'XOF', 'CDF']) ? 0 : 2, ',', ' ') . ' ' . (in_array(strtoupper((string) $currency), ['XAF', 'XOF']) ? 'FCFA' : $currency);
    $tabs = [
        'pending' => ['En attente', 'fa-clock', 'bg-yellow-500 text-dark-100'],
        'processing' => ['En cours', 'fa-spinner', 'bg-blue-500 text-white'],
        'completed' => ['Payés', 'fa-check-circle', 'bg-green-500 text-white'],
        'failed' => ['Échoués / refusés', 'fa-times-circle', 'bg-red-500 text-white'],
        'cancelled' => ['Annulés', 'fa-ban', 'bg-gray-500 text-white'],
        'all' => ['Tous', 'fa-list', null],
    ];
    $filterUrl = fn (array $override) => route('admin.payouts.requests', array_filter(array_merge(['status' => $status, 'method' => $method, 'mode' => $mode, 'q' => $search], $override), fn ($v) => $v !== null && $v !== ''));
@endphp

@section('title', 'Demandes de retrait')
@section('header', 'Demandes de retrait')

@section('content')
<div class="p-6 space-y-6">
    <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-white">Demandes de retrait</h1>
            <p class="text-gray-400 mt-1 max-w-3xl">
                Un retrait part automatiquement quand les fonds restent sur leur rail (carte → IBAN Stripe, Mobile Money → numéro).
                Sinon c'est une <span class="text-gray-200">demande manuelle</span> : le portefeuille du vendeur est déjà débité, ASSO verse le net sous {{ $delayHours }} h en moyenne.
            </p>
        </div>
        <a href="{{ route('admin.payouts.accounts') }}" class="px-3 py-2 text-sm rounded-lg bg-dark-200 hover:bg-dark-300 text-gray-200 whitespace-nowrap">
            <i class="fas fa-address-card mr-1"></i>Comptes de retrait
        </a>
    </div>

    @if($overdue > 0)
        <div class="p-4 rounded-xl bg-red-500/10 border border-red-500/30 text-red-300 text-sm">
            <i class="fas fa-exclamation-triangle mr-2"></i>
            {{ $overdue }} demande(s) manuelle(s) ont dépassé le délai annoncé de {{ $delayHours }} h.
        </div>
    @endif

    <!-- Statuts -->
    <div class="bg-dark-100 rounded-xl shadow-lg p-4">
        <div class="flex gap-2 flex-wrap">
            @foreach($tabs as $key => [$label, $icon, $badge])
                <a href="{{ $filterUrl(['status' => $key]) }}"
                   class="px-4 py-2.5 rounded-lg transition-all text-sm {{ $status === $key ? 'bg-gradient-to-r from-primary-500 to-primary-600 text-white shadow-md' : 'bg-dark-50 text-gray-300 hover:bg-dark-200' }}">
                    <i class="fas {{ $icon }} mr-1.5"></i>{{ $label }}
                    @if($badge)
                        <span class="ml-1.5 px-2 py-0.5 text-xs font-bold rounded-full {{ $badge }}">{{ $counts[$key] ?? 0 }}</span>
                    @endif
                </a>
            @endforeach
        </div>

        <form method="GET" action="{{ route('admin.payouts.requests') }}" class="mt-4 grid grid-cols-1 md:grid-cols-4 gap-3">
            <input type="hidden" name="status" value="{{ $status }}">
            <select name="method" class="px-3 py-2 rounded-lg bg-dark-50 border border-dark-200 text-gray-200 text-sm">
                <option value="">Toutes les méthodes</option>
                <option value="mobile_money" @selected($method === 'mobile_money')>Mobile Money</option>
                <option value="bank_transfer" @selected($method === 'bank_transfer')>Virement bancaire (carte / IBAN)</option>
            </select>
            <select name="mode" class="px-3 py-2 rounded-lg bg-dark-50 border border-dark-200 text-gray-200 text-sm">
                <option value="">Automatiques et manuels</option>
                <option value="manual" @selected($mode === 'manual')>Demandes manuelles (≈ {{ $delayHours }} h)</option>
                <option value="automatic" @selected($mode === 'automatic')>Automatiques</option>
            </select>
            <input type="search" name="q" value="{{ $search }}" placeholder="Vendeur, e-mail, téléphone, référence…"
                   class="px-3 py-2 rounded-lg bg-dark-50 border border-dark-200 text-gray-200 text-sm">
            <div class="flex gap-2">
                <button class="flex-1 px-4 py-2 rounded-lg bg-primary-600 hover:bg-primary-700 text-white text-sm font-medium"><i class="fas fa-filter mr-1"></i>Filtrer</button>
                <a href="{{ route('admin.payouts.requests', ['status' => $status]) }}" class="px-3 py-2 rounded-lg bg-dark-50 text-gray-300 hover:bg-dark-200 text-sm">Effacer</a>
            </div>
        </form>
    </div>

    <!-- Liste -->
    @if($withdrawals->isEmpty())
        <div class="bg-dark-100 rounded-xl shadow-lg p-12 text-center">
            <i class="fas fa-hand-holding-dollar text-6xl text-gray-600 mb-4"></i>
            <h3 class="text-xl font-semibold text-white mb-2">Aucun retrait</h3>
            <p class="text-gray-400">Aucun retrait ne correspond à ces filtres.</p>
        </div>
    @else
        <div class="grid gap-4">
            @foreach($withdrawals as $w)
                @php
                    $u = $w->user;
                    $isMobile = $w->method === 'mobile_money';
                    $open = in_array($w->status, ['pending', 'processing'], true);
                    $manualOpen = $w->isManual() && $open && $w->provider === 'manual';
                    $late = $manualOpen && $w->expected_by && $w->expected_by->isPast();
                    $statusClass = match($w->status) {
                        'completed' => 'bg-green-500/15 text-green-400 border-green-500/30',
                        'failed' => 'bg-red-500/15 text-red-400 border-red-500/30',
                        'cancelled' => 'bg-gray-500/15 text-gray-300 border-gray-500/30',
                        'processing' => 'bg-blue-500/15 text-blue-300 border-blue-500/30',
                        default => 'bg-yellow-500/15 text-yellow-400 border-yellow-500/30',
                    };
                    $bank = $bankAccounts[$w->user_id] ?? null;
                @endphp
                <div class="bg-dark-100 rounded-xl shadow-lg {{ $late ? 'ring-1 ring-red-500/50' : '' }}">
                    <div class="p-5 flex flex-col lg:flex-row lg:items-start gap-5">
                        <!-- Montants -->
                        <div class="lg:w-56 shrink-0">
                            <div class="flex items-center gap-2 text-sm text-gray-400">
                                <i class="fas {{ $isMobile ? 'fa-mobile-alt' : 'fa-university' }}"></i>
                                {{ $isMobile ? 'Mobile Money' : 'Virement bancaire' }}
                            </div>
                            <p class="text-2xl font-bold text-white mt-1">{{ $money($w->amount_sent, $w->currency) }}</p>
                            <p class="text-xs text-gray-500">à verser · débité {{ $money($w->amount_requested, $w->currency) }}
                                @if((float) $w->commission_amount > 0) · frais {{ $money($w->commission_amount, $w->currency) }} @endif
                            </p>
                            @if($w->payout_amount && $w->payout_currency && $w->payout_currency !== $w->currency)
                                <p class="text-xs text-gray-400 mt-1">≈ {{ number_format((float) $w->payout_amount, 2, ',', ' ') }} {{ $w->payout_currency }}</p>
                            @endif
                        </div>

                        <!-- Vendeur + destination -->
                        <div class="flex-1 min-w-0 space-y-1 text-sm">
                            <p class="text-white font-semibold">
                                {{ $u ? (trim($u->first_name . ' ' . $u->last_name) ?: 'Utilisateur #' . $u->id) : 'Utilisateur supprimé' }}
                                <span class="text-gray-500 font-normal">{{ $u?->email }} {{ $u?->phone ? '· ' . $u->phone : '' }}</span>
                            </p>
                            <p class="text-gray-300">
                                <span class="text-gray-500">Vers :</span>
                                <span class="font-mono select-all">{{ $w->payment_account }}</span>
                                @if($isMobile && $w->payment_method) <span class="text-gray-500">({{ $w->payment_method }})</span> @endif
                                @if($w->payment_account_name) <span class="text-gray-500">· titulaire {{ $w->payment_account_name }}</span> @endif
                            </p>
                            @if(!$isMobile && $w->payment_method === 'iban' && $bank)
                                <p class="text-gray-400 text-xs">
                                    @if($bank->bic) BIC <span class="font-mono select-all">{{ $bank->bic }}</span> @endif
                                    @if($bank->bank_name) · {{ $bank->bank_name }} @endif
                                    · pays {{ $bank->country }}
                                </p>
                            @endif
                            <p class="text-gray-500 text-xs">
                                Réf. <span class="font-mono">{{ $w->transaction_reference }}</span>
                                · demandé le {{ $w->created_at->displayTz()->format('d/m/Y H:i') }}
                                @if($manualOpen && $w->expected_by)
                                    · <span class="{{ $late ? 'text-red-400 font-semibold' : '' }}">à traiter avant le {{ $w->expected_by->displayTz()->format('d/m/Y H:i') }}</span>
                                @endif
                            </p>
                            <div class="flex flex-wrap gap-2 pt-1">
                                <span class="px-2 py-0.5 rounded text-xs {{ $w->isManual() ? 'bg-orange-500/15 text-orange-300' : 'bg-dark-50 text-gray-300' }}">
                                    {{ $w->isManual() ? 'Demande manuelle' : 'Automatique' }}
                                </span>
                                @if($w->funds_origin)
                                    <span class="px-2 py-0.5 rounded text-xs bg-dark-50 text-gray-300">
                                        Fonds : {{ ['card' => 'carte', 'mobile_money' => 'Mobile Money', 'mixed' => 'carte + Mobile Money'][$w->funds_origin] ?? $w->funds_origin }}
                                    </span>
                                @endif
                                @if($w->isManual() && ($label = WRC::reasonLabel($w->failure_code)))
                                    <span class="px-2 py-0.5 rounded text-xs bg-dark-50 text-gray-400">{{ $label }}</span>
                                @endif
                            </div>
                            @if($w->admin_notes)
                                <p class="text-xs text-gray-400 whitespace-pre-line pt-1"><i class="fas fa-sticky-note mr-1"></i>{{ $w->admin_notes }}</p>
                            @endif
                            @if(in_array($w->status, ['failed', 'cancelled']) && $w->failure_reason)
                                <p class="text-xs text-red-300 pt-1"><i class="fas fa-info-circle mr-1"></i>{{ $w->failure_reason }}</p>
                            @endif
                            @if($w->status === 'completed')
                                <p class="text-xs text-green-300 pt-1">
                                    Payé le {{ optional($w->completed_at)->displayTz()?->format('d/m/Y H:i') }}
                                    @if($w->payout_reference) · réf. <span class="font-mono">{{ $w->payout_reference }}</span> @endif
                                    @if($w->processor) · par {{ trim($w->processor->first_name . ' ' . $w->processor->last_name) }} @endif
                                </p>
                            @endif
                        </div>

                        <!-- Statut + actions -->
                        <div class="lg:w-72 shrink-0 flex flex-col items-stretch lg:items-end gap-3">
                            <span class="self-start lg:self-end px-3 py-1 rounded-full text-xs font-semibold border {{ $statusClass }}">{{ WRC::statusLabel($w->status) }}</span>

                            @if($manualOpen && $isAdmin)
                                <div class="flex flex-col gap-2 w-full">
                                    @if($w->status === 'pending')
                                        <form method="POST" action="{{ route('admin.payouts.take', $w) }}">
                                            @csrf
                                            <button class="w-full px-3 py-2 rounded-lg bg-dark-50 hover:bg-dark-200 text-gray-200 text-sm"><i class="fas fa-user-check mr-1"></i>Prendre en charge</button>
                                        </form>
                                    @endif

                                    @if($isMobile || ($u && $u->stripe_account_status === 'approved' && $w->payment_method === 'stripe_connect'))
                                        <form method="POST" action="{{ route('admin.payouts.execute', $w) }}"
                                              data-confirm="Envoyer {{ $money($w->amount_sent, $w->currency) }} par {{ $isMobile ? 'Mobile Money' : 'Stripe' }} maintenant ?">
                                            @csrf
                                            <button class="w-full px-3 py-2 rounded-lg bg-primary-600 hover:bg-primary-700 text-white text-sm font-medium">
                                                <i class="fas fa-paper-plane mr-1"></i>Envoyer via {{ $isMobile ? 'Mobile Money' : 'Stripe' }}
                                            </button>
                                        </form>
                                    @endif

                                    <details class="bg-dark-50 rounded-lg">
                                        <summary class="px-3 py-2 cursor-pointer text-sm text-green-300"><i class="fas fa-check mr-1"></i>Marquer payé (versé à la main)</summary>
                                        <form method="POST" action="{{ route('admin.payouts.complete', $w) }}" class="p-3 space-y-2">
                                            @csrf
                                            <input name="payout_reference" required maxlength="120" placeholder="Référence du virement / transfert"
                                                   class="w-full px-3 py-2 rounded bg-dark-100 border border-dark-200 text-gray-200 text-sm">
                                            <input name="note" maxlength="500" placeholder="Note interne (facultatif)"
                                                   class="w-full px-3 py-2 rounded bg-dark-100 border border-dark-200 text-gray-200 text-sm">
                                            <button class="w-full px-3 py-2 rounded bg-green-600 hover:bg-green-700 text-white text-sm">Confirmer le paiement</button>
                                        </form>
                                    </details>

                                    <details class="bg-dark-50 rounded-lg">
                                        <summary class="px-3 py-2 cursor-pointer text-sm text-red-300"><i class="fas fa-times mr-1"></i>Refuser et recréditer</summary>
                                        <form method="POST" action="{{ route('admin.payouts.reject', $w) }}" class="p-3 space-y-2">
                                            @csrf
                                            <textarea name="reason" required minlength="5" maxlength="500" rows="2" placeholder="Motif envoyé au vendeur (ex. IBAN au nom d'un tiers)"
                                                      class="w-full px-3 py-2 rounded bg-dark-100 border border-dark-200 text-gray-200 text-sm"></textarea>
                                            <button class="w-full px-3 py-2 rounded bg-red-600 hover:bg-red-700 text-white text-sm">Refuser la demande</button>
                                        </form>
                                    </details>
                                </div>
                            @elseif($manualOpen)
                                <p class="text-xs text-gray-500 text-right">Traitement réservé aux administrateurs.</p>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-2">{{ $withdrawals->links() }}</div>
    @endif

    <!-- Frais -->
    <div class="bg-dark-100 rounded-xl shadow-lg p-6" id="frais">
        <h2 class="text-lg font-semibold text-white"><i class="fas fa-percent mr-2 text-primary-400"></i>Frais et réglages des retraits</h2>
        <p class="text-sm text-gray-400 mt-1">Retenus automatiquement sur chaque retrait (automatique ou manuel) : le vendeur reçoit le montant demandé moins les frais. Les frais sont crédités au compte ASSO une fois le versement réglé.</p>

        <form method="POST" action="{{ route('admin.payouts.fees') }}" class="mt-5 space-y-5">
            @csrf
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                @foreach(['mobile' => ['mobile_money', 'Mobile Money', 'fa-mobile-alt'], 'bank' => ['bank_transfer', 'Virement bancaire (IBAN)', 'fa-university']] as $key => [$methodKey, $label, $icon])
                    <fieldset class="p-4 rounded-lg bg-dark-50 border border-dark-200">
                        <legend class="px-2 text-sm font-semibold text-gray-200"><i class="fas {{ $icon }} mr-1"></i>{{ $label }}</legend>
                        <div class="grid grid-cols-2 gap-3">
                            <label class="text-xs text-gray-400">Pourcentage (%)
                                <input type="number" step="0.01" min="0" max="50" name="withdrawal_fee_{{ $key }}_percent"
                                       value="{{ old('withdrawal_fee_' . $key . '_percent', $fees[$methodKey]['percent']) }}" @disabled(!$isAdmin)
                                       class="mt-1 w-full px-3 py-2 rounded bg-dark-100 border border-dark-200 text-gray-200 text-sm">
                            </label>
                            <label class="text-xs text-gray-400">Montant fixe (FCFA)
                                <input type="number" step="1" min="0" name="withdrawal_fee_{{ $key }}_fixed"
                                       value="{{ old('withdrawal_fee_' . $key . '_fixed', $fees[$methodKey]['fixed']) }}" @disabled(!$isAdmin)
                                       class="mt-1 w-full px-3 py-2 rounded bg-dark-100 border border-dark-200 text-gray-200 text-sm">
                            </label>
                        </div>
                        <p class="text-xs text-gray-500 mt-2">Ex. : pour 10 000 FCFA → frais de {{ $money(10000 * $fees[$methodKey]['percent'] / 100 + $fees[$methodKey]['fixed'], 'XAF') }}.</p>
                    </fieldset>
                @endforeach
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                <label class="text-xs text-gray-400">Délai moyen annoncé d'une demande manuelle (heures)
                    <input type="number" min="1" max="720" name="withdrawal_manual_delay_hours" value="{{ old('withdrawal_manual_delay_hours', $delayHours) }}" @disabled(!$isAdmin)
                           class="mt-1 w-full px-3 py-2 rounded bg-dark-50 border border-dark-200 text-gray-200 text-sm">
                </label>
                <label class="text-xs text-gray-400">Minimum d'un virement manuel (FCFA)
                    <input type="number" min="0" name="min_manual_bank_withdrawal_amount" value="{{ old('min_manual_bank_withdrawal_amount', $minManualBank) }}" @disabled(!$isAdmin)
                           class="mt-1 w-full px-3 py-2 rounded bg-dark-50 border border-dark-200 text-gray-200 text-sm">
                </label>
                <label class="text-xs text-gray-400">Pays d'IBAN pris en charge par Stripe
                    <input type="text" name="stripe_iban_countries" value="{{ old('stripe_iban_countries', $stripeCountries) }}" @disabled(!$isAdmin)
                           class="mt-1 w-full px-3 py-2 rounded bg-dark-50 border border-dark-200 text-gray-200 text-sm font-mono">
                    <span class="block mt-1 text-gray-500">Les autres IBAN sont orientés vers Mobile Money ou un virement manuel.</span>
                </label>
            </div>

            @if($isAdmin)
                <div class="flex justify-end">
                    <button class="px-5 py-2.5 rounded-lg bg-primary-600 hover:bg-primary-700 text-white text-sm font-medium"><i class="fas fa-save mr-1"></i>Enregistrer</button>
                </div>
            @endif
        </form>
    </div>
</div>
@endsection
