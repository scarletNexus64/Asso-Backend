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
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->string('title')->nullable()->comment('Titre de l\'annonce (optionnel)');
            $table->text('message')->comment('Message de l\'annonce');
            $table->enum('channel', ['sms', 'whatsapp', 'email', 'push'])->comment('Canal d\'envoi');
            $table->enum('target_type', ['all', 'specific'])->default('all')->comment('Type de destinataire');
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade')->comment('Utilisateur cible si target_type = specific');
            $table->enum('status', ['draft', 'sent', 'scheduled'])->default('draft')->comment('Statut de l\'annonce');
            $table->timestamp('scheduled_at')->nullable()->comment('Date d\'envoi programmé');
            $table->timestamp('sent_at')->nullable()->comment('Date d\'envoi effectif');
            $table->integer('sent_count')->default(0)->comment('Nombre d\'envois réussis');
            $table->integer('failed_count')->default(0)->comment('Nombre d\'échecs');
            $table->timestamps();

            // Indexes
            $table->index('status');
            $table->index('channel');
            $table->index('target_type');
            $table->index(['status', 'scheduled_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('announcements');
    }
};
