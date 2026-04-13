<?php

namespace App\Services;

use App\Models\VendorPackage;
use Illuminate\Support\Facades\Storage;

class InvoiceGenerator
{
    /**
     * Generate invoice HTML for a vendor package purchase
     */
    public function generateInvoiceHtml(VendorPackage $vendorPackage): string
    {
        $vendorPackage->load('user', 'package');

        $user = $vendorPackage->user;
        $package = $vendorPackage->package;

        // Prepare package data based on whether it's cumulative or not
        $packageName = $vendorPackage->custom_name ?? $package?->name ?? 'Package';
        $packagePrice = $package?->formatted_price ?? 'Variable';
        $packageStorage = $vendorPackage->package_id && $package
            ? $package->formatted_storage_size
            : number_format($vendorPackage->storage_total_mb, 0) . ' MB';

        $invoiceDate = $vendorPackage->purchased_at->format('d/m/Y');
        $invoiceNumber = 'INV-' . $vendorPackage->payment_reference;

        // Convert logo to base64 for embedding
        $logoPath = public_path('images/logo.png');
        $logoBase64 = '';
        $logoHtml = '<div class="logo-fallback">A</div>';

        if (file_exists($logoPath)) {
            $logoData = base64_encode(file_get_contents($logoPath));
            $logoBase64 = 'data:image/png;base64,' . $logoData;
            $logoHtml = '<img src="' . $logoBase64 . '" alt="ASSO Logo" class="logo">';
        }

        return <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Facture {$invoiceNumber}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            color: #1a1a1a;
            line-height: 1.6;
            padding: 20px;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
        }

        .invoice-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 50px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            border-radius: 12px;
        }

        @media (max-width: 768px) {
            body {
                padding: 10px;
            }
            .invoice-container {
                padding: 20px;
            }
        }

        /* Header */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 40px;
            padding-bottom: 25px;
            border-bottom: 3px solid #F58A3A;
            background: linear-gradient(to right, rgba(245, 138, 58, 0.05), transparent);
            padding: 20px;
            border-radius: 8px;
            margin: -20px -20px 40px -20px;
        }

        .logo-section {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .logo {
            width: 60px;
            height: 60px;
            object-fit: contain;
        }

        .logo-fallback {
            width: 50px;
            height: 50px;
            background: #F58A3A;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
            font-weight: bold;
        }

        .company-info h1 {
            font-size: 28px;
            font-weight: 800;
            color: #F58A3A;
            margin-bottom: 4px;
            letter-spacing: -0.5px;
        }

        .company-info p {
            font-size: 13px;
            color: #6c757d;
            font-weight: 500;
        }

        .invoice-info {
            text-align: right;
        }

        .invoice-title {
            font-size: 14px;
            font-weight: 600;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 8px;
        }

        .invoice-number {
            font-size: 20px;
            font-weight: 700;
            color: #F58A3A;
            margin-bottom: 4px;
        }

        .invoice-date {
            font-size: 13px;
            color: #6c757d;
        }

        @media (max-width: 600px) {
            .header {
                flex-direction: column;
                gap: 20px;
            }
            .invoice-info {
                text-align: left;
            }
        }

        /* Details */
        .details-section {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 35px;
            padding: 25px;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 10px;
            border: 1px solid #e9ecef;
        }

        .detail-block h3 {
            font-size: 11px;
            text-transform: uppercase;
            color: #6c757d;
            margin-bottom: 12px;
            font-weight: 600;
            letter-spacing: 0.5px;
        }

        .detail-block p {
            font-size: 13px;
            margin-bottom: 6px;
            color: #495057;
        }

        .detail-block strong {
            font-weight: 600;
            color: #1a1a1a;
        }

        @media (max-width: 600px) {
            .details-section {
                grid-template-columns: 1fr;
                gap: 20px;
            }
        }

        /* Table Container */
        .table-wrapper {
            width: 100%;
            overflow-x: auto;
            margin-bottom: 30px;
            -webkit-overflow-scrolling: touch;
        }

        /* Table */
        .items-table {
            width: 100%;
            min-width: 500px;
            border-collapse: separate;
            border-spacing: 0;
            border: 1px solid #dee2e6;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .items-table thead {
            background: linear-gradient(135deg, #F58A3A 0%, #e67a2a 100%);
            color: white;
        }

        .items-table th {
            padding: 15px 18px;
            text-align: left;
            font-weight: 700;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .items-table tbody tr {
            border-bottom: 1px solid #e9ecef;
        }

        .items-table tbody tr:last-child {
            border-bottom: none;
        }

        .items-table td {
            padding: 15px;
            font-size: 13px;
            color: #495057;
        }

        .items-table .description {
            color: #6c757d;
            font-size: 12px;
            margin-top: 4px;
        }

        @media (max-width: 600px) {
            .items-table {
                font-size: 11px;
                min-width: 450px;
            }
            .items-table th,
            .items-table td {
                padding: 8px;
            }
            .items-table th {
                font-size: 10px;
            }
            .items-table .description {
                font-size: 10px;
            }
        }

        /* Totals */
        .totals {
            margin-left: auto;
            width: 320px;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 25px;
            border-radius: 10px;
            border: 2px solid #dee2e6;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            font-size: 14px;
            color: #495057;
            font-weight: 500;
        }

        .total-row.grand-total {
            border-top: 3px solid #F58A3A;
            margin-top: 15px;
            padding-top: 15px;
            font-size: 20px;
            font-weight: 800;
            color: #F58A3A;
        }

        @media (max-width: 600px) {
            .totals {
                width: 100%;
                margin-left: 0;
            }
        }

        /* Footer */
        .footer {
            margin-top: 40px;
            padding-top: 25px;
            border-top: 1px solid #e9ecef;
            text-align: center;
        }

        .payment-badge {
            display: inline-block;
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            color: white;
            padding: 12px 30px;
            border-radius: 25px;
            font-size: 14px;
            font-weight: 700;
            margin-bottom: 20px;
            box-shadow: 0 4px 15px rgba(76, 175, 80, 0.4);
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .footer-note {
            font-size: 11px;
            color: #6c757d;
            line-height: 1.6;
        }

        /* Action Buttons */
        .actions {
            margin-top: 30px;
            text-align: center;
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn {
            padding: 12px 30px;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: #F58A3A;
            color: white;
        }

        .btn-primary:hover {
            background: #e67a2a;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(245, 138, 58, 0.3);
        }

        .btn-secondary {
            background: white;
            color: #495057;
            border: 2px solid #e9ecef;
        }

        .btn-secondary:hover {
            background: #f8f9fa;
            border-color: #F58A3A;
            color: #F58A3A;
        }

        @media print {
            body {
                background: white;
                padding: 0;
            }
            .invoice-container {
                box-shadow: none;
                padding: 20px;
                border-radius: 0;
            }
            .actions {
                display: none;
            }
        }

        @media (max-width: 600px) {
            .actions {
                flex-direction: column;
            }
            .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="invoice-container">
        <!-- Header -->
        <div class="header">
            <div class="logo-section">
                {$logoHtml}
                <div class="company-info">
                    <h1>ASSO</h1>
                    <p>Plateforme de Commerce Digital</p>
                </div>
            </div>
            <div class="invoice-info">
                <div class="invoice-title">FACTURE</div>
                <div class="invoice-number">{$invoiceNumber}</div>
                <div class="invoice-date">Date: {$invoiceDate}</div>
            </div>
        </div>

        <!-- Details -->
        <div class="details-section">
            <div class="detail-block">
                <h3>Facturé à</h3>
                <p><strong>{$user->name}</strong></p>
                <p>{$user->email}</p>
                <p>{$user->phone}</p>
            </div>
            <div class="detail-block">
                <h3>Informations de paiement</h3>
                <p><strong>Référence:</strong> {$vendorPackage->payment_reference}</p>
                <p><strong>Date d'achat:</strong> {$vendorPackage->purchased_at->format('d/m/Y H:i')}</p>
                <p><strong>Expire le:</strong> {$vendorPackage->expires_at->format('d/m/Y')}</p>
            </div>
        </div>

        <!-- Items Table -->
        <div class="table-wrapper">
            <table class="items-table">
                <thead>
                    <tr>
                        <th>Description</th>
                        <th>Quantité</th>
                        <th style="text-align: right;">Prix unitaire</th>
                        <th style="text-align: right;">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>
                            <strong>{$packageName}</strong>
                            <div class="description">Package de stockage - {$packageStorage}</div>
                            <div class="description">Valide jusqu'au {$vendorPackage->expires_at->format('d/m/Y')}</div>
                        </td>
                        <td>1</td>
                        <td style="text-align: right;">{$packagePrice}</td>
                        <td style="text-align: right;"><strong>{$packagePrice}</strong></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Totals -->
        <div class="totals">
            <div class="total-row">
                <span>Sous-total</span>
                <span>{$packagePrice}</span>
            </div>
            <div class="total-row">
                <span>TVA (0%)</span>
                <span>0 FCFA</span>
            </div>
            <div class="total-row grand-total">
                <span>TOTAL</span>
                <span>{$packagePrice}</span>
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            <div class="payment-badge">✓ PAYÉ</div>
            <p class="footer-note">
                Merci pour votre confiance !<br>
                Cette facture est générée électroniquement et ne nécessite pas de signature.<br>
                Pour toute question, contactez-nous à support@asso.com
            </p>
        </div>

        <!-- Action Buttons -->
        <div class="actions">
            <button class="btn btn-secondary" onclick="window.print()">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display: inline-block; vertical-align: middle; margin-right: 8px;">
                    <polyline points="6 9 6 2 18 2 18 9"></polyline>
                    <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
                    <rect x="6" y="14" width="12" height="8"></rect>
                </svg>
                Imprimer
            </button>
            <button class="btn btn-primary" onclick="downloadPDF()">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display: inline-block; vertical-align: middle; margin-right: 8px;">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                    <polyline points="7 10 12 15 17 10"></polyline>
                    <line x1="12" y1="15" x2="12" y2="3"></line>
                </svg>
                Télécharger PDF
            </button>
        </div>
    </div>

    <script>
        async function downloadPDF() {
            // Check if FlutterChannel is available (WebView from Flutter app)
            if (typeof FlutterChannel !== 'undefined') {
                try {
                    FlutterChannel.postMessage(JSON.stringify({
                        action: 'downloadInvoice',
                        url: window.location.href
                    }));
                    return;
                } catch (err) {
                    console.log('Flutter channel not available:', err);
                }
            }

            // Check if we're in a mobile environment
            const isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);

            if (isMobile && navigator.share) {
                try {
                    await navigator.share({
                        title: document.title,
                        text: 'Voici votre facture ASSO',
                        url: window.location.href
                    });
                    return;
                } catch (err) {
                    console.log('Share cancelled or failed:', err);
                }
            }

            // Fallback: use print dialog
            window.print();
        }

        // Add keyboard shortcut for print
        document.addEventListener('keydown', function(e) {
            if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
                e.preventDefault();
                window.print();
            }
        });
    </script>
</body>
</html>
HTML;
    }

    /**
     * Get the public URL for downloading the invoice
     */
    public function getInvoiceDownloadUrl(VendorPackage $vendorPackage): string
    {
        return route('api.invoices.download', ['vendorPackageId' => $vendorPackage->id]);
    }
}
