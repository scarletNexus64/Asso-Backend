<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P6 — Code commercial saisi lors de la souscription d'un forfait (renseigné dès la
 * création, même en 'pending', pour tracer aussi les tentatives non abouties).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('package_subscriptions', function (Blueprint $table) {
            $table->foreignId('sales_agent_id')->nullable()->after('package_id')
                ->constrained('sales_agents')->nullOnDelete();
            $table->string('sales_code', 32)->nullable()->after('sales_agent_id');
        });
    }

    public function down(): void
    {
        Schema::table('package_subscriptions', function (Blueprint $table) {
            $table->dropForeign(['sales_agent_id']);
            $table->dropColumn(['sales_agent_id', 'sales_code']);
        });
    }
};
