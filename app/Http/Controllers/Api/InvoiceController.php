<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\VendorPackage;
use App\Services\InvoiceService;
use App\Services\InvoiceGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class InvoiceController extends Controller
{
    protected InvoiceService $invoiceService;
    protected InvoiceGenerator $invoiceGenerator;

    public function __construct(InvoiceService $invoiceService, InvoiceGenerator $invoiceGenerator)
    {
        $this->invoiceService = $invoiceService;
        $this->invoiceGenerator = $invoiceGenerator;
    }

    /**
     * Show package invoice
     */
    public function showPackageInvoice($token)
    {
        try {
            // Decode token
            $decoded = base64_decode($token);
            [$vendorPackageId, $paymentReference] = explode(':', $decoded);

            // Find vendor package
            $vendorPackage = VendorPackage::with(['package', 'user'])
                ->where('id', $vendorPackageId)
                ->where('payment_reference', $paymentReference)
                ->firstOrFail();

            // Get wallet transaction
            $transaction = $vendorPackage->user->walletTransactions()
                ->where('reference_type', 'vendor_package')
                ->where('reference_id', $vendorPackage->id)
                ->where('type', 'debit')
                ->first();

            if (!$transaction) {
                return response()->json([
                    'success' => false,
                    'message' => 'Transaction non trouvée',
                ], 404);
            }

            // Generate invoice data
            $invoiceData = $this->invoiceService->generatePackageInvoice($vendorPackage, $transaction);

            return response()->json([
                'success' => true,
                'invoice' => $invoiceData,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Facture non trouvée',
            ], 404);
        }
    }

    /**
     * Download invoice HTML for a vendor package (authenticated)
     */
    public function download(Request $request, $vendorPackageId)
    {
        $user = $request->user();

        try {
            // Find vendor package
            $vendorPackage = VendorPackage::findOrFail($vendorPackageId);

            // Ensure user owns this package
            if ($vendorPackage->user_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès non autorisé à cette facture',
                ], 403);
            }

            Log::info('[INVOICE] Generating invoice', [
                'vendor_package_id' => $vendorPackage->id,
                'user_id' => $user->id,
            ]);

            $html = $this->invoiceGenerator->generateInvoiceHtml($vendorPackage);

            return response($html)
                ->header('Content-Type', 'text/html; charset=UTF-8')
                ->header('Content-Disposition', 'inline; filename="facture-' . $vendorPackage->payment_reference . '.html"');

        } catch (\Exception $e) {
            Log::error('[INVOICE] Error generating invoice', [
                'vendor_package_id' => $vendorPackageId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la génération de la facture',
            ], 500);
        }
    }
}
