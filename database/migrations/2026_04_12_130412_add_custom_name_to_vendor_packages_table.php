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
        Schema::table('vendor_packages', function (Blueprint $table) {
            // Add custom_name field for cumulative packages
            $table->string('custom_name')->nullable()->after('payment_reference');

            // Make package_id nullable for cumulative packages
            $table->foreignId('package_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vendor_packages', function (Blueprint $table) {
            $table->dropColumn('custom_name');

            // Restore package_id as not nullable
            $table->foreignId('package_id')->nullable(false)->change();
        });
    }
};
