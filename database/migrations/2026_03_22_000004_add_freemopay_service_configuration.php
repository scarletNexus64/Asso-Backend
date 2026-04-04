<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\ServiceConfiguration;

return new class extends Migration
{
    public function up(): void
    {
        // Check if freemopay config exists, create if not
        if (!ServiceConfiguration::where('service_name', 'freemopay')->exists()) {
            ServiceConfiguration::create([
                'service_name' => 'freemopay',
                'is_active' => false,
                'configuration' => [
                    'app_key' => '',
                    'secret_key' => '',
                    'callback_url' => '',
                    'mode' => 'sandbox',
                ],
                'description' => 'FreemoPay - Paiement Mobile Money & Orange Money',
            ]);
        }
    }

    public function down(): void
    {
        ServiceConfiguration::where('service_name', 'freemopay')->delete();
    }
};
