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
        Schema::table('shops', function (Blueprint $table) {
            // Certification fields (different from verification)
            $table->boolean('is_certified')->default(false)->after('status');
            $table->timestamp('certified_at')->nullable()->after('is_certified');
            $table->timestamp('certification_expires_at')->nullable()->after('certified_at');
            $table->unsignedBigInteger('certified_by')->nullable()->after('certification_expires_at');

            // Foreign key for certified_by
            $table->foreign('certified_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->dropForeign(['certified_by']);
            $table->dropColumn([
                'is_certified',
                'certified_at',
                'certification_expires_at',
                'certified_by',
            ]);
        });
    }
};
