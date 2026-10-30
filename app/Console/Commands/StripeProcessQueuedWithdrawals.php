<?php

namespace App\Console\Commands;

use App\Models\PlatformWithdrawal;
use App\Services\StripeWithdrawalService;
use Illuminate\Console\Command;

/**
 * Verse les retraits IBAN mis en attente faute de fonds sur le solde Stripe
 * d'ASSO, dès que ce solde le permet (du plus ancien au plus récent).
 */
class StripeProcessQueuedWithdrawals extends Command
{
    protected $signature = 'stripe:process-queued-withdrawals {--limit=50}';

    protected $description = 'Verse les retraits IBAN en attente de fonds plateforme';

    public function handle(StripeWithdrawalService $withdrawals): int
    {
        $queued = PlatformWithdrawal::where('provider', 'stripe')
            ->where('status', 'pending')
            ->whereNull('stripe_transfer_id')
            ->orderBy('id')
            ->limit((int) $this->option('limit'))
            ->get();

        $counts = ['sent' => 0, 'waiting' => 0, 'skipped' => 0];
        foreach ($queued as $withdrawal) {
            $outcome = $withdrawals->processQueued($withdrawal);
            $counts[$outcome] = ($counts[$outcome] ?? 0) + 1;
            // Fonds épuisés : inutile d'essayer les suivantes, plus récentes.
            if ($outcome === 'waiting') {
                break;
            }
        }

        $this->info(sprintf('%d versé(s), %d en attente, %d ignoré(s).', $counts['sent'], $counts['waiting'], $counts['skipped']));

        return self::SUCCESS;
    }
}
