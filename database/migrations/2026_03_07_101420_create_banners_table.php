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
        Schema::create('banners', function (Blueprint $table) {
            $table->id();
            $table->string('title')->nullable()->comment('Titre de la bannière (optionnel)');
            $table->text('description')->nullable()->comment('Texte/description de la bannière (optionnel)');
            $table->string('image_path')->comment('Chemin de l\'image de la bannière');
            $table->string('link')->nullable()->comment('Lien de redirection (optionnel)');
            $table->integer('position')->default(0)->comment('Position d\'affichage (0 = non défini, 1, 2, 3...)');
            $table->boolean('is_active')->default(true)->comment('Bannière active ou non');
            $table->timestamps();

            // Index pour améliorer les performances
            $table->index('is_active');
            $table->index('position');
            $table->index(['is_active', 'position']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('banners');
    }
};
