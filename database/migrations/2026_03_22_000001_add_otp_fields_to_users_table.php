<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('otp_code', 6)->nullable()->after('phone');
            $table->timestamp('otp_expires_at')->nullable()->after('otp_code');
            $table->boolean('is_profile_complete')->default(false)->after('avatar');
            $table->json('preferences')->nullable()->after('is_profile_complete');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['otp_code', 'otp_expires_at', 'is_profile_complete', 'preferences']);
        });
    }
};
