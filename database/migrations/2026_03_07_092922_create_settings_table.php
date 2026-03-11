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
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique()->comment('Clé unique du paramètre');
            $table->text('value')->nullable()->comment('Valeur du paramètre');
            $table->enum('type', ['string', 'text', 'integer', 'boolean', 'json', 'file'])
                  ->default('string')
                  ->comment('Type de données du paramètre');
            $table->string('group', 50)->nullable()->comment('Groupe de paramètres (general, payment, sms, etc.)');
            $table->string('description')->nullable()->comment('Description du paramètre');
            $table->timestamps();

            // Index pour améliorer les performances
            $table->index('group');
            $table->index(['key', 'group']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
