<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * P7 Diaspo : un voyageur peut publier son offre avant la validation de son identité.
 * L'offre est visible avec la mention « Profil non vérifié » jusqu'à une échéance de
 * régularisation (réglée par ASSO), après laquelle elle est retirée si l'identité n'est
 * toujours pas validée. Chaque étape est tracée dans diaspo_verification_events.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('diaspo_offers', function (Blueprint $table) {
            $table->timestamp('verification_deadline_at')->nullable()->after('rejection_reason');
            $table->timestamp('verification_reminder_sent_at')->nullable()->after('verification_deadline_at');
            $table->string('removal_reason')->nullable()->after('verification_reminder_sent_at');
            $table->index('verification_deadline_at');
        });

        // Historique administrable du statut de vérification (identité + offres).
        Schema::create('diaspo_verification_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('diaspo_offer_id')->nullable()->constrained('diaspo_offers')->nullOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event', 60);
            $table->string('status_from', 30)->nullable();
            $table->string('status_to', 30)->nullable();
            $table->text('reason')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'created_at']);
            $table->index(['diaspo_offer_id', 'created_at']);
        });

        // Offres restées « en attente » sous l'ancienne règle (masquées jusqu'à la
        // vérification) : elles sont désormais publiées avec la mention « Profil non
        // vérifié » et reçoivent une échéance comptée à partir de cette bascule.
        $graceDays = (int) (DB::table('settings')->where('key', 'diaspo_verification_grace_days')->value('value') ?? 7);
        DB::table('diaspo_offers')
            ->where('status', 'pending')
            ->where('verification_status', 'pending')
            ->whereNull('deleted_at')
            ->update([
                'status' => 'approved',
                'verification_deadline_at' => now()->addDays(max(1, $graceDays)),
            ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('diaspo_verification_events');

        Schema::table('diaspo_offers', function (Blueprint $table) {
            $table->dropIndex(['verification_deadline_at']);
            $table->dropColumn(['verification_deadline_at', 'verification_reminder_sent_at', 'removal_reason']);
        });
    }
};
