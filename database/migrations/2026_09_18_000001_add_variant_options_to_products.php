<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Groupes d'options affichés au client (Couleur, Taille, Pointure…) avec,
     * pour les couleurs, la pastille hexadécimale choisie par l'équipe ASSO.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->json('variant_options')->nullable()->after('sizes');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('variant_options');
        });
    }
};
