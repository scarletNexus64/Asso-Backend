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
        Schema::create('shop_location_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained('shops')->onDelete('cascade');
            $table->foreignId('vendor_id')->constrained('users')->onDelete('cascade');
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            $table->string('address')->nullable();
            $table->text('reason')->nullable(); // Raison de la demande
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->text('rejection_reason')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            // Index pour optimiser les requêtes
            $table->index(['shop_id', 'status']);
            $table->index('vendor_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shop_location_requests');
    }
};
