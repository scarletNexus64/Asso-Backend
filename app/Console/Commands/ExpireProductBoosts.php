<?php

namespace App\Console\Commands;

use App\Models\ProductBoost;
use App\Services\ProductBoostService;
use Illuminate\Console\Command;

/**
 * Asso Ads — clôture des campagnes arrivées à échéance.
 *
 * Une campagne dont le quota s'épuise se clôt toute seule au moment de la
 * dernière impression servie (ProductBoostService::consume). Reste le cas
 * inverse : l'échéance atteinte sans que le quota soit consommé — un produit
 * peu diffusé, ou les annonces coupées en admin. Ce passage les ferme.
 *
 * Le scope `servable()` du feed exclut déjà les campagnes périmées : cette
 * commande ne corrige pas la diffusion, elle rend l'historique du vendeur
 * exact (« Terminée » plutôt qu'« En diffusion » indéfiniment).
 */
class ExpireProductBoosts extends Command
{
    protected $signature = 'ads:expire-boosts';

    protected $description = 'Clôt les campagnes de sponsoring arrivées à échéance';

    public function handle(ProductBoostService $service): int
    {
        $expired = $service->expireOverdue();

        if ($expired > 0) {
            $this->info("{$expired} campagne(s) clôturée(s) pour échéance.");
        }

        $running = ProductBoost::where('status', ProductBoost::ACTIVE)->count();
        $this->line("Campagnes encore en diffusion : {$running}");

        return self::SUCCESS;
    }
}
