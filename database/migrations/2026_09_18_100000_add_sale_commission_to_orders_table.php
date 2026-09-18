<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Commission ASSO sur la VENTE (distincte de la commission livraison `delivery_commission`).
 *
 * Figée à la création de la commande (taux issu des plages `commission_ranges` à cet
 * instant) : une modification ultérieure des plages par l'admin ne change jamais le
 * règlement d'une commande déjà passée.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('sale_commission_rate', 5, 2)->nullable()->after('delivery_commission');
            $table->decimal('sale_commission', 12, 2)->default(0)->after('sale_commission_rate');
            $table->decimal('vendor_net_amount', 12, 2)->nullable()->after('sale_commission');
            // Horodatage du règlement vendeur/livreur/ASSO : garde-fou anti double-crédit.
            $table->timestamp('settled_at')->nullable()->after('vendor_net_amount');
            $table->timestamp('refunded_at')->nullable()->after('cancelled_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['sale_commission_rate', 'sale_commission', 'vendor_net_amount', 'settled_at', 'refunded_at']);
        });
    }
};
