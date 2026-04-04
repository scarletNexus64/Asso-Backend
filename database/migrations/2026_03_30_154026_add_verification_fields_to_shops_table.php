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
            // Verification fields
            $table->timestamp('verified_at')->nullable()->after('status');
            $table->foreignId('verified_by')->nullable()->constrained('users')->onDelete('set null')->after('verified_at');

            // Rejection fields
            $table->text('rejection_reason')->nullable()->after('verified_by');
            $table->timestamp('rejected_at')->nullable()->after('rejection_reason');
            $table->foreignId('rejected_by')->nullable()->constrained('users')->onDelete('set null')->after('rejected_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->dropForeign(['verified_by']);
            $table->dropForeign(['rejected_by']);
            $table->dropColumn([
                'verified_at',
                'verified_by',
                'rejection_reason',
                'rejected_at',
                'rejected_by',
            ]);
        });
    }
};
