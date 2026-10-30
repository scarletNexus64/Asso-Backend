<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PayoutAccount;
use App\Services\IbanValidator;
use App\Services\KPayCatalog;
use App\Services\WithdrawalPolicy;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Coordonnées de retrait enregistrées.
 *
 * Mobile Money (retrait automatique KPay / ElgioPay) :
 * GET    /v1/wallet/payout-account  → compte enregistré (ou null)
 * PUT    /v1/wallet/payout-account  → crée / remplace
 * DELETE /v1/wallet/payout-account  → supprime
 *
 * IBAN :
 * POST   /v1/wallet/iban/check      → l'IBAN est-il valide, et Stripe peut-il y verser ?
 * GET|PUT|DELETE /v1/wallet/bank-account → IBAN viré à la main par ASSO (banque que
 *        Stripe ne prend pas en charge). Un IBAN compatible Stripe passe par
 *        l'inscription Stripe (/v1/stripe/connect), seule autorisée en France.
 */
class PayoutAccountController extends Controller
{
    public function show(Request $request)
    {
        $account = PayoutAccount::where('user_id', $request->user()->id)
            ->where('type', PayoutAccount::TYPE_MOBILE_MONEY)
            ->first();

        return response()->json([
            'success' => true,
            'data' => $account?->toApiArray(),
        ]);
    }

    public function upsert(Request $request)
    {
        if (is_string($request->input('phone_number'))) {
            $request->merge(['phone_number' => preg_replace('/[\s+\-().]/', '', $request->input('phone_number'))]);
        }

        $validated = $request->validate([
            'provider' => ['required', 'string', Rule::in(array_keys(KPayCatalog::PROVIDERS))],
            // Numéro international sans « + » : indicatif + numéro local.
            'phone_number' => ['required', 'string', 'regex:/^\d{8,15}$/'],
            'account_holder' => ['nullable', 'string', 'max:120'],
        ], [
            'provider.in' => __('wallet.payout_account.provider_unsupported'),
            'phone_number.regex' => __('wallet.payout_account.phone_invalid'),
        ]);

        $account = PayoutAccount::updateOrCreate(
            ['user_id' => $request->user()->id, 'type' => PayoutAccount::TYPE_MOBILE_MONEY],
            [
                'provider' => $validated['provider'],
                'phone_number' => $validated['phone_number'],
                'account_holder' => $validated['account_holder'] ?? null,
                'currency' => KPayCatalog::currencyForProvider($validated['provider']),
            ]
        );

        return response()->json([
            'success' => true,
            'message' => __('wallet.payout_account.saved'),
            'data' => $account->toApiArray(),
        ]);
    }

    public function destroy(Request $request)
    {
        PayoutAccount::where('user_id', $request->user()->id)
            ->where('type', PayoutAccount::TYPE_MOBILE_MONEY)
            ->delete();

        return response()->json([
            'success' => true,
            'message' => __('wallet.payout_account.deleted'),
            'data' => null,
        ]);
    }

    /**
     * Vérifie un IBAN. Toujours 200 : `valid` et `stripe_supported` disent à l'app
     * quoi proposer (inscription Stripe, Mobile Money conseillé, ou virement manuel).
     */
    public function checkIban(Request $request)
    {
        $request->validate(['iban' => 'required|string|max:64']);

        $check = IbanValidator::check(
            $request->input('iban'),
            IbanValidator::countryHint($request->input('country') ?: $request->user()->country)
        );
        $hours = WithdrawalPolicy::manualDelayHours();

        return response()->json([
            'success' => true,
            'data' => [
                'valid' => $check['valid'],
                'kind' => $check['kind'],
                'formatted' => $check['formatted'],
                'country' => $check['country'],
                'last4' => $check['last4'],
                'stripe_supported' => $check['stripe_supported'],
                'reason' => $check['reason'],
                'message' => $check['message'],
                // Que proposer ensuite.
                'next_step' => !$check['valid'] ? 'fix' : ($check['stripe_supported'] ? 'stripe_onboarding' : 'recommend_mobile_money'),
                'manual_delay_hours' => $hours,
                'manual_notice' => $check['valid'] && !$check['stripe_supported']
                    ? __('wallet.iban.manual_notice', ['hours' => $hours])
                    : null,
            ],
        ]);
    }

    public function showBank(Request $request)
    {
        $account = PayoutAccount::where('user_id', $request->user()->id)
            ->where('type', PayoutAccount::TYPE_BANK_TRANSFER)
            ->first();

        return response()->json([
            'success' => true,
            'data' => $account?->toApiArray(),
        ]);
    }

    public function upsertBank(Request $request)
    {
        $validated = $request->validate([
            'iban' => ['required', 'string', 'max:64'],
            'account_holder' => ['required', 'string', 'min:2', 'max:120'],
            'bic' => ['nullable', 'string', 'regex:/^[A-Za-z]{6}[A-Za-z0-9]{2}([A-Za-z0-9]{3})?$/'],
            'bank_name' => ['nullable', 'string', 'max:120'],
            'currency' => ['nullable', 'string', 'size:3'],
        ], [
            'bic.regex' => __('wallet.iban.bic_invalid'),
        ]);

        $check = IbanValidator::check(
            $validated['iban'],
            IbanValidator::countryHint($request->input('country') ?: $request->user()->country)
        );
        if (!$check['valid']) {
            return response()->json([
                'success' => false,
                'message' => $check['message'],
                'errors' => ['iban' => [$check['message']]],
                'data' => ['reason' => $check['reason']],
            ], 422);
        }

        $account = PayoutAccount::updateOrCreate(
            ['user_id' => $request->user()->id, 'type' => PayoutAccount::TYPE_BANK_TRANSFER],
            [
                'provider' => null,
                'phone_number' => null,
                'iban' => $check['iban'],
                'iban_last4' => $check['last4'],
                'country' => $check['country'],
                'bic' => isset($validated['bic']) ? strtoupper($validated['bic']) : null,
                'bank_name' => $validated['bank_name'] ?? null,
                'account_holder' => $validated['account_holder'],
                'currency' => isset($validated['currency']) ? strtoupper($validated['currency']) : null,
                'stripe_supported' => $check['stripe_supported'],
            ]
        );

        return response()->json([
            'success' => true,
            'message' => __('wallet.iban.saved_manual', ['hours' => WithdrawalPolicy::manualDelayHours()]),
            'data' => $account->toApiArray(),
        ]);
    }

    public function destroyBank(Request $request)
    {
        PayoutAccount::where('user_id', $request->user()->id)
            ->where('type', PayoutAccount::TYPE_BANK_TRANSFER)
            ->delete();

        return response()->json([
            'success' => true,
            'message' => __('wallet.iban.deleted'),
            'data' => null,
        ]);
    }
}
