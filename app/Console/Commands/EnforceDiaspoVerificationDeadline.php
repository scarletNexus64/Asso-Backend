<?php

namespace App\Console\Commands;

use App\Services\DiaspoVerificationService;
use Illuminate\Console\Command;

/**
 * Offres Diaspo « Profil non vérifié » : rappel au voyageur avant l'échéance de
 * régularisation, puis retrait des offres dont l'identité n'a pas été validée à temps.
 */
class EnforceDiaspoVerificationDeadline extends Command
{
    protected $signature = 'diaspo:enforce-verification-deadline';

    protected $description = 'Rappelle puis retire les offres Diaspo dont l\'identité n\'est pas validée dans le délai';

    public function handle(DiaspoVerificationService $verifications): int
    {
        $reminded = $verifications->sendReminders();
        $removed = $verifications->removeExpiredUnverifiedOffers();

        $this->info("Rappels envoyés : {$reminded} — offres retirées : {$removed}");

        return self::SUCCESS;
    }
}
