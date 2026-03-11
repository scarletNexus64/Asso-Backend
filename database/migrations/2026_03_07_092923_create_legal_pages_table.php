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
        Schema::create('legal_pages', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique()->comment('Slug unique pour l\'URL');
            $table->string('title')->comment('Titre de la page');
            $table->text('content')->nullable()->comment('Contenu de la page légale');
            $table->boolean('is_active')->default(true)->comment('Page active ou non');
            $table->integer('order')->default(0)->comment('Ordre d\'affichage');
            $table->timestamps();

            // Index
            $table->index('slug');
            $table->index(['is_active', 'order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('legal_pages');
    }
};
