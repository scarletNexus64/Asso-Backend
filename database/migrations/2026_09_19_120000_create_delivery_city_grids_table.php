<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P4 — Grille urbaine zone à zone d'un partenaire (ex. SOLEX Douala) : zones de
 * quartiers, véhicules (moto, tricycle, 600 kg, 1 t) et prix zone de départ →
 * zone d'arrivée pour chaque véhicule.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_city_grids', function (Blueprint $table) {
            $table->id();
            $table->foreignId('deliverer_company_id')->constrained('deliverer_companies')->cascadeOnDelete();
            $table->string('city');
            $table->string('country', 2)->default('CM');
            // [{"code":1,"label":"Zone 1","quarters":["Bali","Bonapriso",…]}]
            $table->json('zones');
            // [{"code":"moto","label":"Moto","max_weight_kg":20,"lead_time":"1 h à 3 h","prices":{"1-1":1000,"1-2":1000,…}}]
            $table->json('vehicles');
            // Zone de l'agence du partenaire : départ du dernier kilomètre après un trajet interurbain.
            $table->unsignedSmallInteger('agency_zone')->nullable();
            $table->decimal('asso_commission', 12, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['deliverer_company_id', 'city']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('delivery_city_grid_id')->nullable()->after('delivery_route_id')
                ->constrained('delivery_city_grids')->nullOnDelete();
            $table->string('delivery_vehicle', 30)->nullable()->after('delivery_city_grid_id');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('delivery_city_grid_id');
            $table->dropColumn('delivery_vehicle');
        });
        Schema::dropIfExists('delivery_city_grids');
    }
};
