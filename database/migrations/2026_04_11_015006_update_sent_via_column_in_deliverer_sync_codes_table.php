<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Drop and recreate the enum column with new values
        DB::statement("ALTER TABLE deliverer_sync_codes MODIFY sent_via ENUM('email', 'sms', 'whatsapp', 'all') NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert back to old enum values
        DB::statement("ALTER TABLE deliverer_sync_codes MODIFY sent_via ENUM('email', 'sms', 'both') NULL");
    }
};
