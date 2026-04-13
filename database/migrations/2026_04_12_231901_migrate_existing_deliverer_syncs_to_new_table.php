<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * This migration migrates existing deliverer synchronizations from the old system
     * to the new deliverer_code_syncs table for better multi-user sync tracking.
     */
    public function up(): void
    {
        // Get all used sync codes with user_id and company_id
        $existingSyncs = DB::table('deliverer_sync_codes')
            ->where('is_used', true)
            ->whereNotNull('user_id')
            ->whereNotNull('company_id')
            ->get();

        $migratedCount = 0;
        $skippedCount = 0;

        foreach ($existingSyncs as $syncCode) {
            // Check if this sync already exists in the new table
            $exists = DB::table('deliverer_code_syncs')
                ->where('sync_code_id', $syncCode->id)
                ->where('user_id', $syncCode->user_id)
                ->exists();

            if ($exists) {
                $skippedCount++;
                continue;
            }

            // Create entry in new table
            DB::table('deliverer_code_syncs')->insert([
                'sync_code_id' => $syncCode->id,
                'user_id' => $syncCode->user_id,
                'company_id' => $syncCode->company_id,
                'is_active' => true, // Existing syncs are considered active
                'is_banned' => false,
                'synced_at' => $syncCode->used_at ?? $syncCode->created_at,
                'unsynced_at' => null,
                'banned_at' => null,
                'banned_by' => null,
                'ban_reason' => null,
                'created_at' => $syncCode->created_at,
                'updated_at' => $syncCode->updated_at,
            ]);

            $migratedCount++;
        }

        // Log the migration results
        \Log::info("========================================");
        \Log::info("📦 DELIVERER SYNC MIGRATION COMPLETED");
        \Log::info("========================================");
        \Log::info("✅ Migrated: {$migratedCount} syncs");
        \Log::info("⏭️  Skipped (already exists): {$skippedCount} syncs");
        \Log::info("📊 Total processed: " . ($migratedCount + $skippedCount));
        \Log::info("========================================");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Delete only the migrated records (those that match existing sync codes)
        $syncCodeIds = DB::table('deliverer_sync_codes')
            ->where('is_used', true)
            ->whereNotNull('user_id')
            ->pluck('id');

        $deleted = DB::table('deliverer_code_syncs')
            ->whereIn('sync_code_id', $syncCodeIds)
            ->delete();

        \Log::info("Rollback: Deleted {$deleted} migrated sync records");
    }
};
