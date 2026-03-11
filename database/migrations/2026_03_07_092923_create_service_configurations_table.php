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
        Schema::create('service_configurations', function (Blueprint $table) {
            $table->id();
            $table->string('service_name')->unique()->comment('Nom du service (whatsapp, nexaah_sms, fedapay, paypal)');
            $table->boolean('is_active')->default(false)->comment('Service activé ou non');
            $table->json('configuration')->nullable()->comment('Configuration JSON du service');
            $table->text('description')->nullable()->comment('Description du service');
            $table->timestamps();

            // Index
            $table->index('service_name');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_configurations');
    }
};
