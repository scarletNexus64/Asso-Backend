<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Setting;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add OTP default service setting
        Setting::updateOrCreate(
            ['key' => 'otp_default_service', 'group' => 'sms'],
            [
                'value' => 'auto',
                'type' => 'string',
                'description' => 'Service OTP par défaut (auto, whatsapp, sms)',
            ]
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove OTP default service setting
        Setting::where('key', 'otp_default_service')
            ->where('group', 'sms')
            ->delete();
    }
};
