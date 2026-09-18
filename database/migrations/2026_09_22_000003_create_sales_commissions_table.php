<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P6 — Commission due à un commercial pour une souscription payée.
 *
 * Une seule commission par souscription. Les données de la vente (code, forfait,
 * montant, référence, taux, commission) sont FIGÉES à la création : l'historique
 * reste juste même si le forfait, le taux ou le code changent ensuite.
 * Statut : due (à payer) → paid (payée) | cancelled (annulée).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_commissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_agent_id')->constrained('sales_agents')->cascadeOnDelete();
            $table->foreignId('package_subscription_id')->unique()
                ->constrained('package_subscriptions')->cascadeOnDelete();
            $table->foreignId('vendor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('package_id')->nullable()->constrained('packages')->nullOnDelete();

            // Données figées de la vente
            $table->string('sales_code', 32);
            $table->string('package_name');
            $table->string('package_type')->nullable();
            $table->decimal('amount_paid_xaf', 12, 2);
            $table->string('payment_method');
            $table->string('transaction_reference')->nullable();
            $table->timestamp('sold_at');
            $table->decimal('rate', 5, 2);
            $table->decimal('commission_amount', 12, 2);

            // Suivi du versement au commercial (hors plateforme)
            $table->string('status')->default('due');
            $table->string('payout_reference')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('cancel_reason')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['sales_agent_id', 'status']);
            $table->index('sold_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_commissions');
    }
};
