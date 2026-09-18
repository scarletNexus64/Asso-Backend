<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P8 — Statistiques boutiques : journal des interactions acheteurs
 * (visite de boutique, consultation de produit, prise de contact).
 * Les commandes, ventes et chiffre d'affaires se calculent depuis order_items.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_analytics_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained('shops')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event_type', 30); // shop_view | product_view | contact
            $table->string('visitor_hash', 64);
            $table->string('source', 30)->nullable(); // app | api
            $table->timestamp('created_at')->useCurrent();

            $table->index(['shop_id', 'event_type', 'created_at']);
            $table->index(['product_id', 'event_type']);
            $table->index(['visitor_hash', 'event_type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_analytics_events');
    }
};
