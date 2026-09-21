<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Asso Ads — campagnes de sponsoring d'un produit.
 *
 * Un vendeur achète un forfait `packages.type = 'boost'` pour UN de ses produits.
 * Le forfait fixe les deux limites de la campagne :
 *  - `impressions_quota` (= packages.reach_users) : nombre de vues servies achetées ;
 *  - `ends_at` (= now + packages.duration_days) : échéance.
 * La campagne s'arrête au premier des deux atteint (quota épuisé OU date dépassée).
 *
 * Les compteurs sont dénormalisés sur la ligne (et non recalculés depuis
 * shop_analytics_events) : le feed lit `impressions_served` à chaque requête,
 * un COUNT() y serait intenable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_boosts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('shop_id')->constrained('shops')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('package_id')->nullable()->constrained('packages')->nullOnDelete();
            $table->foreignId('package_subscription_id')->nullable()
                ->constrained('package_subscriptions')->nullOnDelete();

            // Conditions achetées, figées à l'achat (le forfait peut changer de prix ensuite).
            $table->unsignedInteger('impressions_quota');
            $table->unsignedInteger('duration_days');
            $table->decimal('amount_xaf', 12, 2)->default(0);

            // Consommation servie par le feed.
            $table->unsignedInteger('impressions_served')->default(0);
            $table->unsignedInteger('clicks')->default(0);

            $table->string('status', 20)->default('active'); // active | completed | expired | cancelled
            $table->timestamp('starts_at')->useCurrent();
            $table->timestamp('ends_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            // Sélection des campagnes à servir dans le feed : le prédicat chaud.
            $table->index(['status', 'ends_at']);
            $table->index(['product_id', 'status']);
            $table->index(['user_id', 'status']);
            $table->index(['shop_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_boosts');
    }
};
