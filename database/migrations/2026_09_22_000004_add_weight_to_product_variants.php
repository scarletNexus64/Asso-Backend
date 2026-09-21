<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Poids propre à chaque déclinaison.
 *
 * Le poids n'existait qu'au niveau du produit : une pointure 36 et une 46, un
 * modèle 64 Go et un 1 To étaient facturés au même tarif de livraison. La
 * colonne reste nullable — `null` signifie « utiliser le poids du produit ».
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->decimal('weight', 10, 3)->nullable()->after('stock');
        });
    }

    public function down(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropColumn('weight');
        });
    }
};
