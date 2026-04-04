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
        Schema::table('service_configurations', function (Blueprint $table) {
            // Change service_name from unique to regular index (we'll keep unique but with compound)
            $table->dropUnique(['service_name']);

            // Add service_type column (to match Estuaire Emploi structure)
            $table->string('service_type')->nullable()->after('service_name');

            // WhatsApp Business API specific fields
            $table->text('whatsapp_api_token')->nullable()->after('configuration');
            $table->string('whatsapp_phone_number_id')->nullable()->after('whatsapp_api_token');
            $table->string('whatsapp_api_version')->default('v22.0')->after('whatsapp_phone_number_id');
            $table->string('whatsapp_template_name')->nullable()->after('whatsapp_api_version');
            $table->string('whatsapp_language')->default('fr')->after('whatsapp_template_name');

            // Re-add unique constraint to service_name
            $table->unique('service_name');
        });

        // Migrate existing data: copy service_name to service_type
        DB::table('service_configurations')->update(['service_type' => DB::raw('service_name')]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('service_configurations', function (Blueprint $table) {
            // Drop WhatsApp fields
            $table->dropColumn([
                'service_type',
                'whatsapp_api_token',
                'whatsapp_phone_number_id',
                'whatsapp_api_version',
                'whatsapp_template_name',
                'whatsapp_language',
            ]);
        });
    }
};
