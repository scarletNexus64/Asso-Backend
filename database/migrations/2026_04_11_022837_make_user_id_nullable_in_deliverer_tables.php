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
        // Make user_id nullable in deliverer_companies
        Schema::table('deliverer_companies', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->change();
        });

        // Make user_id nullable in deliverer_sync_codes
        Schema::table('deliverer_sync_codes', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert back to NOT NULL
        Schema::table('deliverer_companies', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable(false)->change();
        });

        Schema::table('deliverer_sync_codes', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable(false)->change();
        });
    }
};
