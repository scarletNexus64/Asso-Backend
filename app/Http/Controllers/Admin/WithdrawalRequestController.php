<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\StripeWithdrawalException;
use App\Http\Controllers\Controller;
use App\Models\PayoutAccount;
use App\Models\PlatformWithdrawal;
use App\Models\Setting;
use App\Models\User;
use App\Services\CommissionService;
use App\Services\IbanValidator;
use App\Services\StripeWithdrawalService;
use App\Services\WithdrawalPolicy;
use Illuminate\Http\Request;

/**
 * Retraits des vendeurs et des acheteurs, côté back-office.
 *
 * - Demandes de retrait : tous les retraits (automatiques et manuels), filtrables par
 *   méthode, statut et mode. Une demande manuelle (≈ 48 h) se règle ici : envoi par le
 *   prestataire, ou virement fait à la main puis « Marquer payé », ou refus (le
 *   portefeuille est recrédité).
 * - Comptes de retrait : qui a enregistré un numéro Mobile Money et/ou un IBAN.
 * - Frais de retrait par méthode, appliqués automatiquement à chaque retrait.
 *
 * Les actions qui déplacent de l'argent sont réservées aux administrateurs.
 */
class WithdrawalRequestController extends Controller
{
    private const STATUSES = ['pending', 'processing', 'completed', 'failed', 'cancelled'];

    public function index(Request $request)
    {
        $status = $request->get('status', 'pending');
        $method = $request->get('method');
        $mode = $request->get('mode');
        $search = trim((string) $request->get('q'));

        $platformId = CommissionService::platformAccount()?->id;

        $base = PlatformWithdrawal::query()
            ->whereNotNull('user_id')
            ->when($platformId, fn ($q) => $q->where('user_id', '!=', $platformId))
            ->when(in_array($method, ['mobile_money', 'bank_transfer'], true), fn ($q) => $q->where('method', $method))
            ->when(in_array($mode, ['automatic', 'manual'], true), fn ($q) => $q->where('processing_mode', $mode))
            ->when($search !== '', function ($q) use ($search) {
                $like = '%' . mb_strtolower($search) . '%';
                $q->where(function ($q) use ($like) {
                    $q->whereRaw('LOWER(transaction_reference) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(payment_account) LIKE ?', [$like])
                        ->orWhereHas('user', fn ($u) => $u->whereRaw('LOWER(first_name) LIKE ?', [$like])
                            ->orWhereRaw('LOWER(last_name) LIKE ?', [$like])
                            ->orWhereRaw('LOWER(email) LIKE ?', [$like])
                            ->orWhereRaw('LOWER(phone) LIKE ?', [$like]));
                });
            });

        $counts = (clone $base)->selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status');
        $overdue = (clone $base)->where('processing_mode', 'manual')->whereIn('status', ['pending', 'processing'])
            ->where('expected_by', '<', now())->count();

        $withdrawals = (clone $base)
            ->when(in_array($status, self::STATUSES, true), fn ($q) => $q->where('status', $status))
            ->with(['user:id,first_name,last_name,email,phone', 'processor:id,first_name,last_name'])
            ->orderByRaw("CASE WHEN processing_mode = 'manual' AND status IN ('pending','processing') THEN 0 ELSE 1 END")
            ->orderBy($status === 'pending' ? 'created_at' : 'updated_at', $status === 'pending' ? 'asc' : 'desc')
            ->paginate(25)
            ->withQueryString();

        // IBAN complet pour les demandes manuelles vers un IBAN saisi dans l'app.
        $bankAccounts = PayoutAccount::whereIn('user_id', $withdrawals->pluck('user_id')->filter()->unique())
            ->where('type', PayoutAccount::TYPE_BANK_TRANSFER)
            ->get()
            ->keyBy('user_id');

        return view('admin.withdrawal_requests.index', [
            'withdrawals' => $withdrawals,
            'counts' => $counts,
            'overdue' => $overdue,
            'status' => $status,
            'method' => $method,
            'mode' => $mode,
            'search' => $search,
            'fees' => WithdrawalPolicy::feeConfig(),
            'delayHours' => WithdrawalPolicy::manualDelayHours(),
            'minManualBank' => (float) Setting::get('min_manual_bank_withdrawal_amount', 5000),
            'stripeCountries' => implode(', ', IbanValidator::stripeCountries()),
            'bankAccounts' => $bankAccounts,
            'isAdmin' => ($request->user()->role ?? null) === 'admin',
        ]);
    }

    public function accounts(Request $request)
    {
        $mobile = $request->get('mobile');   // yes | no
        $iban = $request->get('iban');       // stripe | stripe_pending | manual | any | none
        $role = $request->get('role');       // vendor | buyer
        $search = trim((string) $request->get('q'));

        $hasMobile = fn ($q) => $q->where('type', PayoutAccount::TYPE_MOBILE_MONEY);
        $hasManualIban = fn ($q) => $q->where('type', PayoutAccount::TYPE_BANK_TRANSFER);

        $query = User::query()
            ->where(function ($q) use ($hasMobile, $hasManualIban) {
                $q->whereHas('payoutAccounts', $hasMobile)
                    ->orWhereHas('payoutAccounts', $hasManualIban)
                    ->orWhereNotNull('stripe_account_id');
            })
            ->when($mobile === 'yes', fn ($q) => $q->whereHas('payoutAccounts', $hasMobile))
            ->when($mobile === 'no', fn ($q) => $q->whereDoesntHave('payoutAccounts', $hasMobile))
            ->when($iban === 'stripe', fn ($q) => $q->where('stripe_account_status', 'approved'))
            ->when($iban === 'stripe_pending', fn ($q) => $q->whereNotNull('stripe_account_id')->where(fn ($s) => $s->whereNull('stripe_account_status')->orWhereIn('stripe_account_status', ['pending', 'rejected'])))
            ->when($iban === 'manual', fn ($q) => $q->whereHas('payoutAccounts', $hasManualIban))
            ->when($iban === 'any', fn ($q) => $q->where(fn ($s) => $s->where('stripe_account_status', 'approved')->orWhereHas('payoutAccounts', $hasManualIban)))
            ->when($iban === 'none', fn ($q) => $q->where(fn ($s) => $s->whereNull('stripe_account_status')->orWhere('stripe_account_status', '!=', 'approved'))->whereDoesntHave('payoutAccounts', $hasManualIban))
            ->when($role === 'vendor', fn ($q) => $q->whereHas('shops'))
            ->when($role === 'buyer', fn ($q) => $q->whereDoesntHave('shops'))
            ->when($search !== '', function ($q) use ($search) {
                $like = '%' . mb_strtolower($search) . '%';
                $q->where(fn ($s) => $s->whereRaw('LOWER(first_name) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(last_name) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(email) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(phone) LIKE ?', [$like]));
            })
            ->withExists('shops')
            ->with(['payoutAccounts', 'walletBalances'])
            ->latest('updated_at');

        $stats = [
            'mobile' => PayoutAccount::where('type', PayoutAccount::TYPE_MOBILE_MONEY)->count(),
            'manual_iban' => PayoutAccount::where('type', PayoutAccount::TYPE_BANK_TRANSFER)->count(),
            'stripe' => User::where('stripe_account_status', 'approved')->count(),
            'both' => User::whereHas('payoutAccounts', $hasMobile)
                ->where(fn ($s) => $s->where('stripe_account_status', 'approved')->orWhereHas('payoutAccounts', $hasManualIban))
                ->count(),
        ];

        return view('admin.withdrawal_requests.accounts', [
            'users' => $query->paginate(25)->withQueryString(),
            'stats' => $stats,
            'filters' => compact('mobile', 'iban', 'role', 'search'),
            'isAdmin' => ($request->user()->role ?? null) === 'admin',
        ]);
    }

    public function take(Request $request, PlatformWithdrawal $withdrawal, WithdrawalPolicy $policy)
    {
        $this->ensureAdmin($request);
        abort_unless($withdrawal->isManual(), 404);

        $policy->markProcessing($withdrawal, $request->user());

        return back()->with('success', "Demande {$withdrawal->transaction_reference} prise en charge.");
    }

    public function execute(Request $request, PlatformWithdrawal $withdrawal, WithdrawalPolicy $policy)
    {
        $this->ensureAdmin($request);
        abort_unless($withdrawal->isManual(), 404);

        try {
            $policy->executeAutomatically($withdrawal, $request->user());
        } catch (StripeWithdrawalException $e) {
            return back()->with('error', e($e->getMessage()));
        }

        return back()->with('success', "Demande {$withdrawal->transaction_reference} envoyée au prestataire : le statut se mettra à jour automatiquement.");
    }

    public function complete(Request $request, PlatformWithdrawal $withdrawal, WithdrawalPolicy $policy)
    {
        $this->ensureAdmin($request);
        abort_unless($withdrawal->isManual(), 404);

        $validated = $request->validate([
            'payout_reference' => 'required|string|max:120',
            'note' => 'nullable|string|max:500',
        ], [
            'payout_reference.required' => 'Indiquez la référence du virement ou du transfert Mobile Money.',
        ]);

        if (!$policy->complete($withdrawal, $request->user(), $validated['payout_reference'], $validated['note'] ?? null)) {
            return back()->with('error', 'Cette demande n’est plus en attente.');
        }

        return back()->with('success', "Demande {$withdrawal->transaction_reference} marquée payée. Le vendeur est prévenu.");
    }

    public function reject(Request $request, PlatformWithdrawal $withdrawal, WithdrawalPolicy $policy)
    {
        $this->ensureAdmin($request);
        abort_unless($withdrawal->isManual(), 404);

        $validated = $request->validate([
            'reason' => 'required|string|min:5|max:500',
        ], [
            'reason.required' => 'Expliquez au vendeur pourquoi la demande est refusée.',
        ]);

        if (!$policy->reject($withdrawal, $request->user(), $validated['reason'])) {
            return back()->with('error', 'Cette demande n’est plus en attente.');
        }

        return back()->with('success', "Demande {$withdrawal->transaction_reference} refusée : le portefeuille du vendeur est recrédité.");
    }

    public function updateFees(Request $request)
    {
        $this->ensureAdmin($request);

        $validated = $request->validate([
            'withdrawal_fee_mobile_percent' => 'required|numeric|min:0|max:50',
            'withdrawal_fee_mobile_fixed' => 'required|numeric|min:0|max:1000000',
            'withdrawal_fee_bank_percent' => 'required|numeric|min:0|max:50',
            'withdrawal_fee_bank_fixed' => 'required|numeric|min:0|max:1000000',
            'withdrawal_manual_delay_hours' => 'required|integer|min:1|max:720',
            'min_manual_bank_withdrawal_amount' => 'required|numeric|min:0|max:100000000',
            'stripe_iban_countries' => ['nullable', 'string', 'max:500', 'regex:/^\s*([A-Za-z]{2}\s*(,\s*[A-Za-z]{2}\s*)*)?$/'],
        ], [
            'stripe_iban_countries.regex' => 'Saisissez des codes pays à deux lettres séparés par des virgules (ex. FR, BE, DE).',
        ]);

        foreach ($validated as $key => $value) {
            if ($key === 'stripe_iban_countries') {
                $value = implode(',', array_filter(array_map(fn ($c) => strtoupper(trim($c)), explode(',', (string) $value))));
            }
            Setting::set($key, (string) $value, 'string', 'payment', Setting::where('key', $key)->value('description'));
        }

        return back()->with('success', 'Frais et réglages des retraits enregistrés.');
    }

    private function ensureAdmin(Request $request): void
    {
        abort_unless(($request->user()->role ?? null) === 'admin', 403, 'Action réservée aux administrateurs.');
    }

    /** Libellés partagés par les vues. */
    public static function statusLabel(?string $status): string
    {
        return match ($status) {
            'pending' => 'En attente',
            'processing' => 'En cours',
            'completed' => 'Payé',
            'failed' => 'Échoué / refusé',
            'cancelled' => 'Annulé',
            default => (string) $status,
        };
    }

    public static function reasonLabel(?string $code): ?string
    {
        return match ($code) {
            'card_funds_to_mobile' => 'Fonds encaissés par carte → retrait Mobile Money',
            'mobile_funds_to_bank' => 'Fonds encaissés par Mobile Money → virement IBAN',
            'iban_not_stripe' => 'IBAN non pris en charge par Stripe',
            'rail_unavailable' => 'Prestataire indisponible au moment de la demande',
            default => null,
        };
    }
}
