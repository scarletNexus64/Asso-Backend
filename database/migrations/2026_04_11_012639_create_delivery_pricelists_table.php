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
        Schema::create('delivery_pricelists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_zone_id')->constrained('delivery_zones')->onDelete('cascade');
            $table->enum('pricing_type', ['fixed', 'weight_category', 'volumetric_weight']);
            $table->json('pricing_data'); // Stocke les configurations de prix
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('delivery_zone_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('delivery_pricelists');
    }
};
