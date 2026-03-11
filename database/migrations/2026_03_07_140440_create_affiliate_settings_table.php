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
        Schema::create('affiliate_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('is_enabled')->default(true);
            $table->integer('max_levels')->default(2); // 2 ou 3 niveaux
            $table->decimal('level_1_percentage', 5, 2)->default(10.00); // % niveau 1
            $table->decimal('level_2_percentage', 5, 2)->default(5.00); // % niveau 2
            $table->decimal('level_3_percentage', 5, 2)->default(2.50); // % niveau 3
            $table->decimal('minimum_withdrawal', 10, 2)->default(5000); // Minimum pour retrait
            $table->boolean('auto_approve_commissions')->default(false);
            $table->text('terms_and_conditions')->nullable();
            $table->timestamps();
        });

        // Insérer les paramètres par défaut
        DB::table('affiliate_settings')->insert([
            'is_enabled' => true,
            'max_levels' => 2,
            'level_1_percentage' => 10.00,
            'level_2_percentage' => 5.00,
            'level_3_percentage' => 2.50,
            'minimum_withdrawal' => 5000,
            'auto_approve_commissions' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('affiliate_settings');
    }
};
