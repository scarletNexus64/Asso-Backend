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
        // Table des conversations entre utilisateurs
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user1_id')->constrained('users')->onDelete('cascade')->comment('Premier utilisateur');
            $table->foreignId('user2_id')->constrained('users')->onDelete('cascade')->comment('Deuxième utilisateur');
            $table->foreignId('product_id')->nullable()->constrained()->onDelete('set null')->comment('Produit concerné');
            $table->timestamp('last_message_at')->nullable()->comment('Date du dernier message');
            $table->timestamps();

            // Index pour optimiser les recherches
            $table->index(['user1_id', 'user2_id']);
            $table->index('last_message_at');
        });

        // Table des messages dans les conversations
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->onDelete('cascade');
            $table->foreignId('sender_id')->constrained('users')->onDelete('cascade')->comment('Expéditeur du message');
            $table->text('message')->comment('Contenu du message');
            $table->boolean('is_read')->default(false)->comment('Message lu ou non');
            $table->timestamp('read_at')->nullable()->comment('Date de lecture');
            $table->timestamps();

            // Index
            $table->index('conversation_id');
            $table->index('sender_id');
            $table->index('is_read');
        });

        // Table de tracking des clics de contact (WhatsApp et Appels)
        Schema::create('contact_clicks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade')->comment('Utilisateur qui clique');
            $table->foreignId('seller_id')->constrained('users')->onDelete('cascade')->comment('Vendeur contacté');
            $table->foreignId('product_id')->nullable()->constrained()->onDelete('set null')->comment('Produit concerné');
            $table->enum('contact_type', ['whatsapp', 'call'])->comment('Type de contact');
            $table->string('ip_address', 45)->nullable()->comment('Adresse IP');
            $table->text('user_agent')->nullable()->comment('User agent du navigateur');
            $table->timestamps();

            // Index
            $table->index('seller_id');
            $table->index('contact_type');
            $table->index('created_at');
            $table->index(['seller_id', 'contact_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contact_clicks');
        Schema::dropIfExists('messages');
        Schema::dropIfExists('conversations');
    }
};
