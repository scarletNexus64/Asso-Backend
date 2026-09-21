<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Asso Ads — consommation quotidienne d'une campagne.
 *
 * Agrégat par jour, et non une ligne par impression : le feed sert des milliers
 * d'impressions par campagne, une table événementielle exploserait sans rien
 * apporter de plus que la courbe affichée au vendeur.
 *
 * Les vues de fiche produit et les contacts restent lus depuis
 * shop_analytics_events (product_view / contact), croisés sur la fenêtre de
 * campagne : on ne duplique ici que ce que cette table ne sait pas, à savoir
 * l'impression servie dans le feed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_boost_daily_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_boost_id')->constrained('product_boosts')->cascadeOnDelete();
            $table->date('date');
            $table->unsignedInteger('impressions')->default(0);
            $table->unsignedInteger('clicks')->default(0);
            $table->timestamps();

            $table->unique(['product_boost_id', 'date']);
            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_boost_daily_stats');
    }
};
