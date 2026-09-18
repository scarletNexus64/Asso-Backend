<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P6 — Commerciaux ASSO.
 *
 * Un commercial n'a pas forcément de compte dans l'application (recrutement en ligne) :
 * il est créé par l'admin et identifié par un code unique saisi par le vendeur au
 * moment de souscrire un forfait. user_id relie le commercial à un compte existant
 * (sert à empêcher l'auto-parrainage).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_agents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            // Code saisi par le vendeur, stocké en MAJUSCULES.
            $table->string('code', 32)->unique();
            // Taux particulier (%) ; null ⇒ Setting sales_commission_rate.
            $table->decimal('commission_rate', 5, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_agents');
    }
};
