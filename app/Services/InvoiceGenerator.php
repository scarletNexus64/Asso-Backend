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

        // Logo path
        $logoUrl = asset('images/logo.png');

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
            line-height: 1.5;
            padding: 20px;
            background: #f8f9fa;
        }

        .invoice-container {
            max-width: 700px;
            margin: 0 auto;
            background: white;
            padding: 40px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            border-radius: 8px;
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
            padding-bottom: 20px;
            border-bottom: 2px solid #F58A3A;
        }

        .logo-section {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .logo {
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
            font-size: 24px;
            font-weight: 700;
            color: #1a1a1a;
            margin-bottom: 2px;
        }

        .company-info p {
            font-size: 12px;
            color: #6c757d;
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
            margin-bottom: 30px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 6px;
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

        /* Table */
        .items-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin-bottom: 25px;
            border: 1px solid #e9ecef;
            border-radius: 6px;
            overflow: hidden;
        }

        .items-table thead {
            background: #F58A3A;
            color: white;
        }

        .items-table th {
            padding: 12px 15px;
            text-align: left;
            font-weight: 600;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
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
                font-size: 12px;
            }
            .items-table th,
            .items-table td {
                padding: 10px;
            }
        }

        /* Totals */
        .totals {
            margin-left: auto;
            width: 280px;
            background: #f8f9fa;
            padding: 20px;
            border-radius: 6px;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            font-size: 13px;
            color: #495057;
        }

        .total-row.grand-total {
            border-top: 2px solid #F58A3A;
            margin-top: 12px;
            padding-top: 12px;
            font-size: 18px;
            font-weight: 700;
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
            padding: 10px 24px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 15px;
            box-shadow: 0 2px 8px rgba(76, 175, 80, 0.3);
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
                <div class="logo">A</div>
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
        function downloadPDF() {
            // Use browser's print-to-PDF functionality
            window.print();
        }
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
