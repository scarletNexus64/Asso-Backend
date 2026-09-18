<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Coordonnées de versement enregistrées pour les retraits Wallet (Mobile Money).
 */
class PayoutAccount extends Model
{
    public const TYPE_MOBILE_MONEY = 'mobile_money';

    protected $fillable = [
        'user_id',
        'type',
        'provider',
        'phone_number',
        'account_holder',
        'currency',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Numéro masqué pour l'affichage (ex. 2376••••0001). */
    public function getMaskedPhoneAttribute(): string
    {
        $phone = (string) $this->phone_number;
        if (strlen($phone) <= 8) {
            return $phone;
        }
        return substr($phone, 0, 4) . str_repeat('•', strlen($phone) - 8) . substr($phone, -4);
    }

    public function toApiArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'provider' => $this->provider,
            'phone_number' => $this->phone_number,
            'masked_phone' => $this->masked_phone,
            'account_holder' => $this->account_holder,
            'currency' => $this->currency,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
