<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Achat de plusieurs morceaux d'un terrain en une seule réservation :
 * N morceaux d'un même lot, ou tout ce qui reste du terrain.
 * Ajout pur : les réservations existantes gardent unit_id et une quantité de 1.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('real_estate_reservations', function (Blueprint $table) {
            $table->unsignedInteger('quantity')->default(1)->after('unit_id');
            $table->boolean('whole')->default(false)->after('quantity');
        });

        Schema::create('real_estate_reservation_lots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained('real_estate_reservations')->cascadeOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained('real_estate_units')->nullOnDelete();
            $table->string('label')->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedBigInteger('unit_price')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('real_estate_reservation_lots');
        Schema::table('real_estate_reservations', function (Blueprint $table) {
            $table->dropColumn(['quantity', 'whole']);
        });
    }
};
