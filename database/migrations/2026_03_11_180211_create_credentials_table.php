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
        Schema::create('credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained('credential_categories')->onDelete('set null');
            $table->string('name'); // Ex: "Compte Google", "AWS Access Key"
            $table->string('type')->default('password'); // password, api_key, token, ssh_key, etc.
            $table->string('username')->nullable(); // Email, username, etc.
            $table->text('password'); // Chiffré
            $table->text('url')->nullable(); // URL du service
            $table->text('notes')->nullable(); // Notes additionnelles
            $table->json('custom_fields')->nullable(); // Champs personnalisés
            $table->boolean('is_favorite')->default(false);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('credentials');
    }
};
