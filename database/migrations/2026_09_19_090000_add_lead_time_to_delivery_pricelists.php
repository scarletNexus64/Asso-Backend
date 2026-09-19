<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** P4 — délai de livraison d'une zone urbaine (« 24 h », « 2 à 4 h »), affiché à l'acheteur. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_pricelists', function (Blueprint $table) {
            $table->string('lead_time', 60)->nullable()->after('asso_commission');
        });
    }

    public function down(): void
    {
        Schema::table('delivery_pricelists', function (Blueprint $table) {
            $table->dropColumn('lead_time');
        });
    }
};
