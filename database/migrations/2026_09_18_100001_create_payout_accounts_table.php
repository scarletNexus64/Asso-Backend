<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Coordonnées de versement enregistrées (retraits Wallet ASSO).
 *
 * Seul le Mobile Money (KPay payout) est géré ici : le virement bancaire passe par
 * l'IBAN validé via Stripe Connect (colonnes stripe_* de `users`).
 * Un compte par type et par utilisateur.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payout_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 30)->default('mobile_money');
            // Code opérateur KPay (ex. MTN_MOMO_CMR) — détermine pays et devise.
            $table->string('provider', 50);
            // Numéro international sans « + » (ex. 237670000001).
            $table->string('phone_number', 30);
            $table->string('account_holder', 120)->nullable();
            $table->string('currency', 3)->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payout_accounts');
    }
};
