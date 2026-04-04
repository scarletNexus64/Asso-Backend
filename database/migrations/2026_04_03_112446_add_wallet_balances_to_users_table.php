<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Ajouter deux colonnes pour les wallets séparés
            $table->decimal('freemopay_wallet_balance', 15, 2)->default(0)->after('withdrawn_earnings');
            $table->decimal('paypal_wallet_balance', 15, 2)->default(0)->after('freemopay_wallet_balance');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['freemopay_wallet_balance', 'paypal_wallet_balance']);
        });
    }
};
