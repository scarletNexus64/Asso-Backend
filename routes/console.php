<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Jobs\Wallet\CheckPendingDepositsJob;
use App\Jobs\Wallet\CheckPendingWithdrawalsJob;
use App\Jobs\Wallet\CleanupStaleTransactionsJob;
use App\Jobs\UpdateExchangeRatesJob;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/**
 * =====================================================
 * WALLET ASYNC TRANSACTION PROCESSING SCHEDULER
 * =====================================================
 *
 * Ces jobs vérifient automatiquement le statut des dépôts et retraits
 * en interrogeant l'API KPay de manière asynchrone.
 */

// Vérifier les dépôts en attente toutes les 30 secondes
// Les dépôts KPay (Orange Money / MTN MoMo) peuvent prendre quelques secondes à quelques minutes
// Note: Les Jobs sont automatiquement exécutés via le système de queues de Laravel
Schedule::job(new CheckPendingDepositsJob)
    ->everyThirtySeconds()
    ->withoutOverlapping(120); // Max 2 minutes d'exécution

// Vérifier les retraits en attente toutes les minute
// Les retraits peuvent prendre plus de temps (jusqu'à 48 heures)
// Note: Les Jobs sont automatiquement exécutés via le système de queues de Laravel
Schedule::job(new CheckPendingWithdrawalsJob)
    ->everyMinute()
    ->withoutOverlapping(120); // Max 2 minutes d'exécution

// Nettoyer les transactions obsolètes tous les jours à 3h du matin
// Marque comme échouées les transactions bloquées depuis trop longtemps
// Note: Les Jobs sont automatiquement exécutés via le système de queues de Laravel
Schedule::job(new CleanupStaleTransactionsJob)
    ->dailyAt('03:00')
    ->withoutOverlapping(300); // Max 5 minutes d'exécution

/**
 * =====================================================
 * CURRENCY EXCHANGE RATES UPDATE SCHEDULER
 * =====================================================
 *
 * Met à jour les taux de change depuis l'API externe toutes les heures
 * Les taux sont mis en cache pour réduire les appels API
 */
Schedule::job(new UpdateExchangeRatesJob)
    ->hourly()
    ->withoutOverlapping(300); // Max 5 minutes d'exécution

/**
 * =====================================================
 * VIREMENTS IBAN (STRIPE CONNECT) — RÉCONCILIATION
 * =====================================================
 *
 * Le chemin nominal reste le webhook `payout.paid` / `payout.failed`. Ce passage
 * régulier est le filet de sécurité : il clôture les virements restés « en cours »
 * quand un événement s'est perdu ou qu'aucun endpoint public n'est encore déclaré.
 * Sans lui, un vendeur peut voir « virement en cours » indéfiniment alors que
 * l'argent est arrivé sur son compte.
 */
Schedule::command('stripe:reconcile-payouts')
    ->everyFifteenMinutes()
    ->withoutOverlapping(300);

/**
 * =====================================================
 * FORFAITS VENDEURS — RAPPELS D'EXPIRATION
 * =====================================================
 *
 * Rappel à J-7, J-3 et J-1 pour que le vendeur renouvelle avant de perdre
 * la possibilité de publier ses produits.
 */
Schedule::command('packages:notify-expiring')
    ->dailyAt('09:00')
    ->withoutOverlapping(300);

/**
 * =====================================================
 * FORFAITS VENDEURS — SOUSCRIPTIONS EN ATTENTE
 * =====================================================
 *
 * Active les forfaits payés (Mobile Money / carte) dont la confirmation n'a pas été
 * suivie par l'application, et clôt les tentatives abandonnées depuis plus de 24 h.
 */
Schedule::command('packages:reconcile-pending')
    ->everyFiveMinutes()
    ->withoutOverlapping(300);

/**
 * =====================================================
 * DIASPO — ÉCHÉANCE DE RÉGULARISATION DE L'IDENTITÉ
 * =====================================================
 *
 * Une offre publiée par un profil non vérifié reste en ligne avec la mention
 * « Profil non vérifié » jusqu'à l'échéance fixée par ASSO (admin → Vérifications
 * DIASPO). Rappel 48 h avant, puis retrait si l'identité n'est toujours pas validée.
 */
Schedule::command('diaspo:enforce-verification-deadline')
    ->hourly()
    ->withoutOverlapping(300);
