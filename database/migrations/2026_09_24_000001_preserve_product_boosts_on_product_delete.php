<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Asso Ads — une campagne payée doit survivre à la suppression de son produit.
 *
 * `product_id` était en cascade : supprimer un produit effaçait la campagne et
 * ses statistiques journalières. Le chiffre d'affaires sponsoring disparaissait
 * alors rétroactivement des totaux, et le vendeur perdait l'historique de ce
 * qu'il avait payé.
 *
 * On passe en `nullOnDelete` et on conserve le nom du produit sur la ligne :
 * la campagne reste lisible (« Produit supprimé » n'est plus un fallback mort)
 * et la comptabilité reste juste.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_boosts', function (Blueprint $table) {
            // Nom figé à l'achat : un produit renommé ou supprimé ne doit pas
            // rendre l'historique illisible.
            $table->string('product_name')->nullable()->after('product_id');
        });

        // Rattrapage des campagnes déjà en base.
        \Illuminate\Support\Facades\DB::statement(
            'UPDATE product_boosts SET product_name = products.name
             FROM products WHERE products.id = product_boosts.product_id
             AND product_boosts.product_name IS NULL'
        );

        Schema::table('product_boosts', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
            $table->unsignedBigInteger('product_id')->nullable()->change();
            $table->foreign('product_id')->references('id')->on('products')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('product_boosts', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
            $table->dropColumn('product_name');
        });

        // Les campagnes orphelines empêcheraient de restaurer la contrainte.
        \Illuminate\Support\Facades\DB::table('product_boosts')->whereNull('product_id')->delete();

        Schema::table('product_boosts', function (Blueprint $table) {
            $table->unsignedBigInteger('product_id')->nullable(false)->change();
            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
        });
    }
};
