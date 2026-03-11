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
        Schema::create('packages', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['storage', 'boost', 'certification'])->comment('Type de package');
            $table->string('name')->comment('Nom du package');
            $table->text('description')->nullable()->comment('Description du package');
            $table->decimal('price', 10, 2)->comment('Prix en XOF');
            $table->integer('duration_days')->comment('Durée en jours');

            // Spécifique au stockage
            $table->integer('storage_size_mb')->nullable()->comment('Taille de stockage en Mo');

            // Spécifique au boost/sponsoring
            $table->integer('reach_users')->nullable()->comment('Nombre d\'utilisateurs à toucher');

            // Spécifique à la certification
            $table->json('benefits')->nullable()->comment('Avantages de la certification');

            // Autres champs
            $table->boolean('is_active')->default(true)->comment('Package actif ou non');
            $table->boolean('is_popular')->default(false)->comment('Package populaire (badge)');
            $table->integer('order')->default(0)->comment('Ordre d\'affichage');
            $table->timestamps();

            // Index
            $table->index('type');
            $table->index('is_active');
            $table->index('order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('packages');
    }
};
