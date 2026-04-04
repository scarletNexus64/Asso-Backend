<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Pour PostgreSQL, on doit d'abord supprimer puis recréer la colonne enum
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('payment_method');
        });

        Schema::table('transactions', function (Blueprint $table) {
            // Recréer payment_method avec les nouvelles valeurs
            $table->enum('payment_method', ['cash', 'card', 'mobile', 'paypal', 'visa', 'mastercard', 'fedapay'])->default('cash')->after('status');

            // Ajouter nouveaux champs
            $table->string('transaction_id')->nullable()->unique()->after('reference')->comment('ID unique de la transaction (externe)');
            $table->decimal('fees', 15, 2)->default(0)->after('amount')->comment('Frais de transaction');
            $table->decimal('net_amount', 15, 2)->nullable()->after('fees')->comment('Montant net (après frais)');
            $table->string('currency', 3)->default('XOF')->after('net_amount')->comment('Devise (XOF = CFA)');
            $table->enum('type', ['purchase', 'refund', 'exchange'])->default('purchase')->after('status')->comment('Type de transaction');
            $table->string('external_reference')->nullable()->after('type')->comment('Référence externe (PayPal, FedaPay, etc.)');
            $table->text('description')->nullable()->after('external_reference')->comment('Description de la transaction');
            $table->json('metadata')->nullable()->after('description')->comment('Données supplémentaires (JSON)');
            $table->string('payer_email')->nullable()->after('metadata')->comment('Email du payeur');
            $table->string('payer_name')->nullable()->after('payer_email')->comment('Nom du payeur');
            $table->timestamp('completed_at')->nullable()->after('payer_name')->comment('Date de complétion');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn([
                'transaction_id',
                'fees',
                'net_amount',
                'currency',
                'type',
                'external_reference',
                'description',
                'metadata',
                'payer_email',
                'payer_name',
                'completed_at'
            ]);
        });
    }
};
