<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\Setting;
use App\Models\WalletBalance;
use App\Models\WalletTransaction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Marque comme « encaissés par carte » les fonds des ventes payées par carte libérées
 * AVANT le suivi de l'origine des fonds. Une seule exécution (verrou en réglage) ;
 * le trigger SQL borne le résultat au disponible actuel de chaque vendeur.
 *
 * php artisan wallet:backfill-card-funds --dry-run
 */
class BackfillCardFunds extends Command
{
    protected $signature = 'wallet:backfill-card-funds {--dry-run : Affiche sans rien écrire} {--force : Relance malgré une exécution précédente}';

    protected $description = 'Rattache à la part « carte » du portefeuille les ventes payées par carte déjà libérées';

    public function handle(): int
    {
        if (Setting::get('card_funds_backfilled_at') && !$this->option('force')) {
            $this->warn('Déjà exécuté le ' . Setting::get('card_funds_backfilled_at') . ' (utilisez --force pour relancer).');
            return self::SUCCESS;
        }

        $perVendor = [];
        Order::where('vendor_funds_status', Order::VENDOR_FUNDS_RELEASED)
            ->whereNotNull('vendor_funds_holder_id')
            ->orderBy('id')
            ->chunkById(200, function ($orders) use (&$perVendor) {
                foreach ($orders as $order) {
                    $ratio = $order->cardPaidRatio();
                    if ($ratio <= 0) {
                        continue;
                    }
                    // Montant réellement débloqué au vendeur pour cette commande.
                    $released = (float) WalletTransaction::where('user_id', $order->vendor_funds_holder_id)
                        ->where('reference_type', 'order')
                        ->where('reference_id', $order->id)
                        ->where('type', 'unlock')
                        ->sum('amount');
                    if ($released <= 0) {
                        continue;
                    }
                    $perVendor[$order->vendor_funds_holder_id] = ($perVendor[$order->vendor_funds_holder_id] ?? 0) + $released * $ratio;
                }
            });

        if ($perVendor === []) {
            $this->info('Aucune vente payée par carte à rattacher.');
        }

        $rows = [];
        foreach ($perVendor as $userId => $amount) {
            $balance = WalletBalance::where('user_id', $userId)->where('currency', 'XAF')->first();
            $rows[] = [
                $userId,
                number_format($amount, 0, ',', ' '),
                number_format((float) ($balance?->available ?? 0), 0, ',', ' '),
                number_format(min($amount, max(0, (float) ($balance?->available ?? 0))), 0, ',', ' '),
            ];
        }
        $this->table(['Vendeur', 'Ventes carte libérées', 'Disponible XAF', 'Part carte retenue'], $rows);

        if ($this->option('dry-run')) {
            $this->info('Simulation : rien n’a été écrit.');
            return self::SUCCESS;
        }

        DB::transaction(function () use ($perVendor) {
            foreach ($perVendor as $userId => $amount) {
                // Remplace (pas d'addition) pour rester idempotent avec --force.
                WalletBalance::where('user_id', $userId)->where('currency', 'XAF')
                    ->update(['card_funds' => round($amount, 2)]);
            }
            Setting::set('card_funds_backfilled_at', now()->toIso8601String(), 'string', 'payment', 'Rattrapage de la part carte des portefeuilles (wallet:backfill-card-funds)');
        });

        $this->info(count($perVendor) . ' portefeuille(s) mis à jour.');

        return self::SUCCESS;
    }
}
