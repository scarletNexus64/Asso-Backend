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
        Schema::create('deliverer_code_syncs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sync_code_id')->constrained('deliverer_sync_codes')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('company_id')->constrained('deliverer_companies')->onDelete('cascade');
            $table->boolean('is_active')->default(true); // Si désynchronisé = false
            $table->boolean('is_banned')->default(false); // Si banni = true
            $table->timestamp('synced_at')->useCurrent();
            $table->timestamp('unsynced_at')->nullable();
            $table->timestamp('banned_at')->nullable();
            $table->foreignId('banned_by')->nullable()->constrained('users')->onDelete('set null'); // Admin qui a banni
            $table->string('ban_reason')->nullable();
            $table->timestamps();

            // Index pour améliorer les performances
            $table->index('sync_code_id');
            $table->index('user_id');
            $table->index('company_id');
            $table->index('is_active');
            $table->index('is_banned');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deliverer_code_syncs');
    }
};
