<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Commission ASSO en MAJORATION : chaque ligne de commande fige le prix du vendeur
 * (ce qu'il touchera) à côté du prix payé par l'acheteur (unit_price / total_price,
 * majorés de la commission). Null = commande antérieure, sans majoration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->decimal('seller_unit_price', 12, 2)->nullable()->after('total_price');
            $table->decimal('seller_total_price', 12, 2)->nullable()->after('seller_unit_price');
            $table->decimal('commission_rate', 5, 2)->nullable()->after('seller_total_price');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn(['seller_unit_price', 'seller_total_price', 'commission_rate']);
        });
    }
};
