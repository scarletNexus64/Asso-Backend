<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Models\PackageSubscription;
use App\Models\VendorPackage;
use App\Services\WalletService;
use App\Services\InvoiceService;
use App\Services\InvoiceGenerator;
use App\Services\FcmService;
use App\Services\PackageSubscriptionService;
use App\Services\PaymentMethodService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PackageController extends Controller
{
    protected WalletService $walletService;
    protected InvoiceService $invoiceService;
    protected InvoiceGenerator $invoiceGenerator;
    protected FcmService $fcmService;
    protected PackageSubscriptionService $packageSubscriptionService;

    public function __construct(
        WalletService $walletService,
        InvoiceService $invoiceService,
        InvoiceGenerator $invoiceGenerator,
        FcmService $fcmService,
        PackageSubscriptionService $packageSubscriptionService
    ) {
        $this->walletService = $walletService;
        $this->invoiceService = $invoiceService;
        $this->invoiceGenerator = $invoiceGenerator;
        $this->fcmService = $fcmService;
        $this->packageSubscriptionService = $packageSubscriptionService;
    }

    /**
     * List all active storage packages
     */
    public function index()
    {
        $packages = Package::ofType('storage')
            ->active()
            ->ordered()
            ->get();

        return response()->json([
            'success' => true,
            'packages' => $packages->map(fn($package) => [
                'id' => $package->id,
                'name' => $package->name,
                'description' => $package->description,
                'price' => (float) $package->price,
                'formatted_price' => $package->formatted_price,
                'duration_days' => $package->duration_days,
                'formatted_duration' => $package->formatted_duration,
                'storage_size_mb' => $package->storage_size_mb,
                'formatted_storage_size' => $package->formatted_storage_size,
                'benefits' => $package->benefits,
                'is_popular' => (bool) $package->is_popular,
            ]),
        ]);
    }

    /**
     * List all active certification packages
     */
    public function certificationPackages()
    {
        $packages = Package::ofType('certification')
            ->active()
            ->ordered()
            ->get();

        return response()->json([
            'success' => true,
            'packages' => $packages->map(fn($package) => [
                'id' => $package->id,
                'name' => $package->name,
                'description' => $package->description,
                'price' => (float) $package->price,
                'formatted_price' => $package->formatted_price,
                'duration_days' => $package->duration_days,
                'formatted_duration' => $package->formatted_duration,
                'benefits' => $package->benefits,
                'is_popular' => (bool) $package->is_popular,
            ]),
        ]);
    }

    /**
     * Souscrire à un forfait (stockage ou certification).
     *
     * POST /v1/packages/subscribe
     *   payment_mode = wallet        → débit immédiat du solde Wallet ASSO (activation instantanée)
     *                  kpay_direct   → Mobile Money (USSD), confirmation par polling
     *                  stripe_direct → carte (Payment Sheet), confirmation par polling
     * Rétro-compat : payment_mode absent + wallet_type=kpay ⇒ wallet.
     */
    public function subscribe(Request $request)
    {
        $validated = $request->validate([
            'package_id' => 'required|exists:packages,id',
            'wallet_type' => 'nullable|in:kpay',
            'payment_mode' => 'nullable|in:wallet,kpay_direct,stripe_direct',
            'provider' => 'required_if:payment_mode,kpay_direct|nullable|string',
            'phone_number' => 'required_if:payment_mode,kpay_direct|nullable|string',
        ]);

        $user = $request->user();
        $package = Package::findOrFail($validated['package_id']);
        $paymentMode = $validated['payment_mode'] ?? 'wallet';

        try {
            $this->packageSubscriptionService->assertSubscribable($user, $package);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        if (in_array($paymentMode, ['kpay_direct', 'stripe_direct'], true)) {
            return $this->subscribeDirect($request, $user, $package, $paymentMode);
        }

        // ── Paiement par SOLDE Wallet ASSO ──
        $canPay = $this->walletService->canPayWithWallet($user, (float) $package->price, 'kpay');
        if (!$canPay['can_pay']) {
            return response()->json([
                'success' => false,
                'code' => 'insufficient_balance',
                'message' => $canPay['message'],
                'data' => [
                    'current_balance' => $canPay['available_balance'],
                    'required_amount' => (float) $package->price,
                    'missing_amount' => $canPay['missing_amount'],
                ],
            ], 422);
        }

        try {
            $subscription = $this->packageSubscriptionService->payWithWallet($user, $package);
        } catch (\Exception $e) {
            Log::error('[PackageController] Souscription wallet échouée', ['user_id' => $user->id, 'error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'message' => $package->type === 'certification'
                ? 'Certification activée ! Votre boutique affiche désormais le badge vérifié.'
                : 'Forfait activé ! Votre espace de stockage est disponible.',
            'payment_mode' => 'wallet',
            'subscription_id' => $subscription->id,
            'status' => $subscription->status, // paid
            'payment_reference' => $subscription->payment_reference,
            'data' => $this->subscriptionPayload($subscription),
        ], 201);
    }

    /**
     * Souscription d'un package par RAIL DIRECT (kpay_direct / stripe_direct).
     *
     * Crée un « intent » d'abonnement (PackageSubscription en 'pending') et initie le
     * paiement chez le PSP. Le VendorPackage n'est créé/cumulé qu'à la confirmation du
     * paiement (polling GET /v1/packages/subscription/{id}/payment-status). Aucune
     * validation vendeur ici : l'abonnement s'active dès que l'argent est encaissé.
     */
    private function subscribeDirect(Request $request, $user, Package $package, string $paymentMode)
    {
        // Garde-fou : le rail carte (Stripe natif) n'est proposé que s'il est réellement
        // fonctionnel (clés configurées + activé). Sinon on bloque immédiatement.
        if ($paymentMode === 'stripe_direct' && !PaymentMethodService::isEnabled('stripe')) {
            return response()->json([
                'success' => false,
                'message' => "Le paiement par carte bancaire (Stripe) n'est pas disponible pour le moment. Veuillez choisir un autre moyen de paiement.",
            ], 422);
        }

        try {
            $subscription = $this->packageSubscriptionService->createDirect(
                user: $user,
                package: $package,
                paymentMode: $paymentMode,
                kpayProvider: $request->input('provider'),
                kpayPhone: $request->input('phone_number'),
            );
        } catch (\Exception $e) {
            Log::error('[PackageController] ❌ Direct subscription init failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => match ($paymentMode) {
                'kpay_direct' => 'Abonnement créé. Validez le paiement sur votre téléphone (USSD).',
                'stripe_direct' => 'Abonnement créé. Finalisez le paiement par carte.',
                default => 'Abonnement créé.',
            },
            'payment_mode' => $paymentMode,
            'subscription_id' => $subscription->id,
            'status' => $subscription->status, // pending
            'payment_reference' => $subscription->payment_reference,
            // Carte native (stripe_direct) : confirmation via Payment Sheet, puis polling.
            'client_secret' => $paymentMode === 'stripe_direct' ? ($subscription->client_secret ?? null) : null,
            'payment_intent_id' => $paymentMode === 'stripe_direct' ? ($subscription->payment_intent_id ?? null) : null,
            'publishable_key' => $paymentMode === 'stripe_direct' ? ($subscription->stripe_publishable_key ?? null) : null,
        ], 201);
    }

    /**
     * Statut de paiement d'un abonnement direct (pollable).
     * GET /v1/packages/subscription/{id}/payment-status
     *
     * Re-vérifie chez le PSP et active l'abonnement (crée/cumule le VendorPackage) dès
     * que le paiement est confirmé. Renvoie {status: pending|paid|failed, ...}.
     */
    public function subscriptionPaymentStatus(Request $request, $id)
    {
        $subscription = PackageSubscription::where('user_id', $request->user()->id)
            ->findOrFail($id);

        if ($subscription->isPending()) {
            $this->packageSubscriptionService->checkAndConfirm($subscription);
            $subscription->refresh();
        }

        return response()->json([
            'success' => true,
            'data' => $this->subscriptionPayload($subscription),
        ]);
    }

    /** Représentation API d'une souscription (statut + résultat de l'activation). */
    private function subscriptionPayload(PackageSubscription $subscription): array
    {
        $vendorPackage = $subscription->vendor_package_id
            ? VendorPackage::find($subscription->vendor_package_id)
            : null;
        $package = Package::find($subscription->package_id);

        return [
            'subscription_id' => $subscription->id,
            // pending | paid | failed
            'status' => $subscription->status,
            'payment_mode' => $subscription->payment_method,
            'payment_reference' => $subscription->payment_reference,
            'package_type' => $package?->type,
            'package_name' => $package?->name,
            'amount' => (float) $subscription->amount_xaf,
            'certification_expires_at' => $subscription->metadata['certification_expires_at'] ?? null,
            'vendor_package_id' => $subscription->vendor_package_id,
            'vendor_package' => $vendorPackage ? [
                'id' => $vendorPackage->id,
                'storage_total_mb' => (float) $vendorPackage->storage_total_mb,
                'storage_remaining_mb' => (float) $vendorPackage->storage_remaining_mb,
                'expires_at' => $vendorPackage->expires_at->toIso8601String(),
                'status' => $vendorPackage->status,
                'is_cumulative' => $vendorPackage->package_id === null,
            ] : null,
        ];
    }

    /**
     * Get current active package for the authenticated user
     */
    public function currentPackage(Request $request)
    {
        $user = $request->user();
        $vendorPackage = $user->activeVendorPackage;

        if (!$vendorPackage) {
            return response()->json([
                'success' => false,
                'message' => 'Aucun package actif',
                'has_package' => false,
            ], 404);
        }

        // Load package relationship only if package_id is not null
        if ($vendorPackage->package_id) {
            $vendorPackage->load('package');
        }

        // Prepare package data based on whether it's cumulative or not
        $packageData = $vendorPackage->package_id && $vendorPackage->package
            ? [
                'id' => $vendorPackage->package->id,
                'name' => $vendorPackage->package->name,
                'description' => $vendorPackage->package->description,
                'price' => (float) $vendorPackage->package->price,
                'formatted_price' => $vendorPackage->package->formatted_price,
                'duration_days' => $vendorPackage->package->duration_days,
                'formatted_duration' => $vendorPackage->package->formatted_duration,
                'storage_size_mb' => $vendorPackage->package->storage_size_mb,
                'formatted_storage_size' => $vendorPackage->package->formatted_storage_size,
            ]
            : [
                'id' => null,
                'name' => $vendorPackage->custom_name ?? 'Espace Cumulé',
                'description' => 'Package personnalisé avec espace cumulé',
                'price' => null,
                'formatted_price' => 'Variable',
                'duration_days' => null,
                'formatted_duration' => 'Personnalisé',
                'storage_size_mb' => (float) $vendorPackage->storage_total_mb,
                'formatted_storage_size' => number_format($vendorPackage->storage_total_mb, 0) . ' MB',
            ];

        return response()->json([
            'success' => true,
            'has_package' => true,
            'vendor_package' => [
                'id' => $vendorPackage->id,
                'storage_total_mb' => (float) $vendorPackage->storage_total_mb,
                'storage_used_mb' => (float) $vendorPackage->storage_used_mb,
                'storage_remaining_mb' => (float) $vendorPackage->storage_remaining_mb,
                'storage_percentage_used' => $vendorPackage->storage_total_mb > 0
                    ? round(($vendorPackage->storage_used_mb / $vendorPackage->storage_total_mb) * 100, 2)
                    : 0,
                'purchased_at' => $vendorPackage->purchased_at->toIso8601String(),
                'expires_at' => $vendorPackage->expires_at->toIso8601String(),
                'days_remaining' => now()->diffInDays($vendorPackage->expires_at, false),
                'status' => $vendorPackage->status,
                'payment_reference' => $vendorPackage->payment_reference,
                'is_cumulative' => $vendorPackage->package_id === null,
                'package' => $packageData,
            ],
        ]);
    }
}
