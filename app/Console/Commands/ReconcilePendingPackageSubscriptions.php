<?php

namespace App\Console\Commands;

use App\Models\PackageSubscription;
use App\Services\PackageSubscriptionService;
use Illuminate\Console\Command;

/**
 * Filet de sécurité des souscriptions payées par rail direct (Mobile Money / carte).
 *
 * Le mobile confirme par polling tant que l'écran est ouvert ; si l'utilisateur le
 * quitte, le paiement peut aboutir sans que le forfait soit activé. Ce passage
 * régulier re-vérifie chez le PSP et active (ou échoue) les souscriptions en attente.
 * Au-delà de 24 h sans référence ni confirmation, la tentative est close.
 */
class ReconcilePendingPackageSubscriptions extends Command
{
    protected $signature = 'packages:reconcile-pending';

    protected $description = 'Active ou clôt les souscriptions de forfaits restées en attente de paiement';

    private const STALE_AFTER_HOURS = 24;

    public function handle(PackageSubscriptionService $service): int
    {
        $confirmed = 0;
        $failed = 0;

        PackageSubscription::where('status', 'pending')
            ->where('created_at', '<', now()->subMinute())
            ->orderBy('id')
            ->each(function (PackageSubscription $sub) use ($service, &$confirmed, &$failed) {
                try {
                    $service->checkAndConfirm($sub);
                } catch (\Throwable $e) {
                    $this->warn("Souscription #{$sub->id} : {$e->getMessage()}");
                }

                $sub->refresh();
                if ($sub->status === 'paid') {
                    $confirmed++;
                    return;
                }
                if ($sub->status === 'pending' && $sub->created_at->lt(now()->subHours(self::STALE_AFTER_HOURS))) {
                    $service->fail($sub, 'expired');
                    $sub->refresh();
                }
                if ($sub->status === 'failed') {
                    $failed++;
                }
            });

        $this->info("Souscriptions activées : {$confirmed} — clôturées : {$failed}");

        return self::SUCCESS;
    }
}
