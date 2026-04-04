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
        Schema::create('platform_withdrawals', function (Blueprint $table) {
            $table->id();

            // User qui demande le retrait (pour les retraits utilisateur)
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');

            // Admin qui effectue le retrait (pour les retraits admin/plateforme)
            $table->foreignId('admin_id')->nullable()->constrained('users')->onDelete('set null');

            // Montants
            $table->decimal('amount_requested', 15, 2);
            $table->decimal('commission_rate', 5, 2)->default(0);
            $table->decimal('commission_amount', 15, 2)->default(0);
            $table->decimal('amount_sent', 15, 2);
            $table->string('currency', 10)->default('XAF');

            // Provider (freemopay ou paypal)
            $table->string('provider')->default('freemopay');

            // Méthode de paiement (om, momo pour FreeMoPay ; paypal pour PayPal)
            $table->string('payment_method');
            $table->string('payment_account'); // Numéro de téléphone ou email PayPal
            $table->string('payment_account_name')->nullable();

            // Statut
            $table->enum('status', ['pending', 'processing', 'completed', 'failed', 'cancelled'])->default('pending');

            // Références
            $table->string('transaction_reference')->unique();

            // FreeMoPay fields
            $table->string('freemopay_reference')->nullable();
            $table->json('freemopay_response')->nullable();

            // PayPal fields
            $table->string('paypal_batch_id')->nullable();
            $table->string('paypal_payout_item_id')->nullable();
            $table->json('paypal_response')->nullable();

            // Erreur / Échec
            $table->string('failure_code')->nullable();
            $table->text('failure_reason')->nullable();

            // Notes admin
            $table->text('admin_notes')->nullable();

            // Timestamps
            $table->timestamp('completed_at')->nullable();
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            // Index
            $table->index('status');
            $table->index('provider');
            $table->index('created_at');
            $table->index('user_id');
            $table->index('admin_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('platform_withdrawals');
    }
};
