<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * This migration updates the existing PostgreSQL ENUM type `sent_via`
     * by replacing it with a new definition that includes additional values.
     */
    public function up(): void
    {
        // Step 1: Rename the existing ENUM type to preserve it temporarily
        DB::statement("ALTER TYPE sent_via RENAME TO sent_via_old");

        // Step 2: Create a new ENUM type with the updated set of values
        DB::statement("CREATE TYPE sent_via AS ENUM ('email', 'sms', 'whatsapp', 'all')");

        // Step 3: Alter the column to use the new ENUM type
        // The USING clause casts existing values to text, then to the new ENUM
        DB::statement("
            ALTER TABLE deliverer_sync_codes
            ALTER COLUMN sent_via DROP DEFAULT,
            ALTER COLUMN sent_via TYPE sent_via
            USING sent_via::text::sent_via
        ");

        // Step 4: Remove the old ENUM type as it is no longer needed
        DB::statement("DROP TYPE sent_via_old");
    }

    /**
     * Reverse the migrations.
     *
     * This rollback restores the original ENUM definition.
     */
    public function down(): void
    {
        // Step 1: Rename the current ENUM type to allow recreation of the original
        DB::statement("ALTER TYPE sent_via RENAME TO sent_via_new");

        // Step 2: Recreate the original ENUM type definition
        DB::statement("CREATE TYPE sent_via AS ENUM ('email', 'sms', 'both')");

        // Step 3: Reapply the original ENUM type to the column
        DB::statement("
            ALTER TABLE deliverer_sync_codes
            ALTER COLUMN sent_via DROP DEFAULT,
            ALTER COLUMN sent_via TYPE sent_via
            USING sent_via::text::sent_via
        ");

        // Step 4: Drop the temporary ENUM type
        DB::statement("DROP TYPE sent_via_new");
    }
};