<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletBalance extends Model
{
    protected $fillable = [
        'user_id',
        'currency',
        'balance',
        'locked_balance',
        'card_funds',
    ];

    protected $casts = [
        'balance' => 'decimal:2',
        'locked_balance' => 'decimal:2',
        'card_funds' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Solde disponible (total - bloqué). */
    public function getAvailableAttribute(): float
    {
        return (float) $this->balance - (float) $this->locked_balance;
    }

    /**
     * Part du disponible encaissée par carte (Stripe) : seule part virable
     * automatiquement vers un IBAN. Bornée au disponible (cf. trigger SQL).
     */
    public function getCardAvailableAttribute(): float
    {
        return max(0.0, min((float) $this->card_funds, $this->available));
    }

    /** Part du disponible encaissée par Mobile Money (ou d'origine interne). */
    public function getMobileAvailableAttribute(): float
    {
        return max(0.0, $this->available - $this->card_available);
    }

    /**
     * Marque `$amount` du disponible comme encaissé par carte (vente payée par carte
     * libérée, recharge par carte). À appeler APRÈS que le solde a été crédité.
     */
    public static function addCardFunds(int $userId, string $currency, float $amount): void
    {
        if ($amount <= 0) {
            return;
        }

        static::where('user_id', $userId)->where('currency', strtoupper($currency))
            ->increment('card_funds', round($amount, 2));
    }

    /** Retire `$amount` de la part carte (virement IBAN automatique). */
    public static function useCardFunds(int $userId, string $currency, float $amount): void
    {
        if ($amount <= 0) {
            return;
        }

        static::where('user_id', $userId)->where('currency', strtoupper($currency))
            ->update(['card_funds' => \Illuminate\Support\Facades\DB::raw(
                'CASE WHEN card_funds > ' . round($amount, 2) . ' THEN card_funds - ' . round($amount, 2) . ' ELSE 0 END'
            )]);
    }
}
