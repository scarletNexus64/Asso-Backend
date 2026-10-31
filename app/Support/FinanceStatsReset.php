<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Carbon;

/**
 * Remise à zéro des compteurs financiers du back-office (Transactions, Revenus).
 * Rien n'est supprimé : les statistiques ne comptent que ce qui suit cette date.
 * Le solde du Wallet ASSO (PlatformWalletSync) n'est jamais concerné.
 */
class FinanceStatsReset
{
    public const KEY = 'finance_stats_reset_at';

    public static function since(): ?Carbon
    {
        $value = Setting::get(self::KEY);
        if (! $value) {
            return null;
        }
        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    public static function resetNow(): Carbon
    {
        $now = now();
        Setting::set(self::KEY, $now->toIso8601String(), 'string', 'finance',
            'Début du comptage des statistiques financières du back-office (données conservées).');

        return $now;
    }
}
