<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Coordonnées de versement enregistrées pour les retraits Wallet :
 * - mobile_money : numéro Mobile Money (retrait automatique KPay / ElgioPay) ;
 * - bank_transfer : IBAN saisi dans l'app, pour une banque que Stripe ne prend pas
 *   en charge. Viré à la main par ASSO (demande traitée en ≈ 48 h).
 *
 * L'IBAN compatible Stripe, lui, est saisi chez Stripe (colonnes stripe_* de users).
 */
class PayoutAccount extends Model
{
    public const TYPE_MOBILE_MONEY = 'mobile_money';
    public const TYPE_BANK_TRANSFER = 'bank_transfer';

    protected $fillable = [
        'user_id',
        'type',
        'provider',
        'phone_number',
        'account_holder',
        'currency',
        'iban',
        'iban_last4',
        'bic',
        'bank_name',
        'country',
        'stripe_supported',
    ];

    protected $casts = [
        'iban' => 'encrypted',
        'stripe_supported' => 'boolean',
    ];

    protected $hidden = ['iban'];

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

    /** IBAN masqué pour l'affichage (ex. CM21 •••• •••• 1234). */
    public function getMaskedIbanAttribute(): ?string
    {
        if (!$this->iban_last4) {
            return null;
        }

        return ($this->country ?: '??') . '•• •••• •••• ' . $this->iban_last4;
    }

    public function toApiArray(): array
    {
        if ($this->type === self::TYPE_BANK_TRANSFER) {
            return [
                'id' => $this->id,
                'type' => $this->type,
                'masked_iban' => $this->masked_iban,
                'iban_last4' => $this->iban_last4,
                'country' => $this->country,
                'bic' => $this->bic,
                'bank_name' => $this->bank_name,
                'account_holder' => $this->account_holder,
                'currency' => $this->currency,
                'stripe_supported' => (bool) $this->stripe_supported,
                'updated_at' => $this->updated_at?->toIso8601String(),
            ];
        }

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
