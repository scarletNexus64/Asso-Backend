<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('asso_product_references', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('country', 100)->nullable();
            $table->string('quarter')->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('asso_product_reference_id')->nullable()->constrained('asso_product_references')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        // La suppression automatique de ces données est volontairement interdite.
        throw new RuntimeException('Cette migration ne peut pas être annulée automatiquement.');
    }
};
