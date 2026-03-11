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
        Schema::create('affiliate_commissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('affiliate_id')->constrained('users')->onDelete('cascade'); // Celui qui reçoit la commission
            $table->foreignId('referred_user_id')->constrained('users')->onDelete('cascade'); // Le filleul qui a généré la commission
            $table->foreignId('transaction_id')->nullable()->constrained('transactions')->onDelete('set null'); // Transaction source
            $table->integer('level')->default(1); // Niveau d'affiliation (1, 2 ou 3)
            $table->decimal('amount', 10, 2); // Montant de la commission
            $table->decimal('percentage', 5, 2); // Pourcentage appliqué
            $table->enum('status', ['pending', 'approved', 'paid', 'rejected'])->default('pending');
            $table->text('description')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('affiliate_commissions');
    }
};
