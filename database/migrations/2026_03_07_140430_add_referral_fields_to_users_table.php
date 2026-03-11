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
        Schema::table('users', function (Blueprint $table) {
            $table->string('referral_code')->unique()->nullable()->after('email');
            $table->foreignId('referred_by_id')->nullable()->constrained('users')->onDelete('set null')->after('referral_code');
            $table->decimal('total_earnings', 10, 2)->default(0)->after('referred_by_id');
            $table->decimal('pending_earnings', 10, 2)->default(0)->after('total_earnings');
            $table->decimal('withdrawn_earnings', 10, 2)->default(0)->after('pending_earnings');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['referred_by_id']);
            $table->dropColumn(['referral_code', 'referred_by_id', 'total_earnings', 'pending_earnings', 'withdrawn_earnings']);
        });
    }
};
