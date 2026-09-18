<?php

namespace App\Console\Commands;

use App\Models\VendorPackage;
use App\Services\FcmService;
use Illuminate\Console\Command;

/**
 * Prévient les vendeurs 7, 3 et 1 jour(s) avant l'expiration de leur forfait :
 * sans forfait actif, ils ne peuvent plus publier ni modifier de produits.
 */
class NotifyExpiringPackages extends Command
{
    protected $signature = 'packages:notify-expiring';

    protected $description = 'Notifie les vendeurs dont le forfait expire bientôt (J-7, J-3, J-1)';

    private const REMINDER_DAYS = [7, 3, 1];

    public function handle(FcmService $fcm): int
    {
        $sent = 0;

        foreach (self::REMINDER_DAYS as $days) {
            // Forfaits qui expirent pendant la journée J+$days (exécution quotidienne).
            $start = now()->startOfDay()->addDays($days);

            VendorPackage::with('user')
                ->where('status', 'active')
                ->whereBetween('expires_at', [$start, $start->copy()->endOfDay()])
                ->each(function (VendorPackage $package) use ($fcm, $days, &$sent) {
                    if (!$package->user) {
                        return;
                    }
                    // Un forfait plus récent prend le relais : pas de rappel inutile.
                    if ($package->user->activeVendorPackage?->expires_at?->gt($package->expires_at)) {
                        return;
                    }
                    $fcm->sendPackageExpiringNotification($package->user, $days);
                    $sent++;
                });
        }

        $this->info("{$sent} rappel(s) d'expiration envoyé(s).");

        return self::SUCCESS;
    }
}
