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
        Schema::create('delivery_zones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('deliverer_company_id')->constrained('deliverer_companies')->onDelete('cascade');
            $table->string('name');
            $table->json('zone_data'); // Stocke les coordonnées du polygone OSM
            $table->decimal('center_latitude', 10, 8)->nullable();
            $table->decimal('center_longitude', 11, 8)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('deliverer_company_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('delivery_zones');
    }
};
