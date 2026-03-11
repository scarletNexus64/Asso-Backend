<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContactClick extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'seller_id',
        'product_id',
        'contact_type',
        'ip_address',
        'user_agent',
    ];

    /**
     * Utilisateur qui a cliqué
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Vendeur contacté
     */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    /**
     * Produit concerné
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Scope pour les clics WhatsApp
     */
    public function scopeWhatsapp($query)
    {
        return $query->where('contact_type', 'whatsapp');
    }

    /**
     * Scope pour les appels
     */
    public function scopeCall($query)
    {
        return $query->where('contact_type', 'call');
    }

    /**
     * Scope par vendeur
     */
    public function scopeBySeller($query, $sellerId)
    {
        return $query->where('seller_id', $sellerId);
    }

    /**
     * Scope par période
     */
    public function scopeBetweenDates($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * Obtenir le libellé du type de contact
     */
    public function getContactTypeLabelAttribute(): string
    {
        return match($this->contact_type) {
            'whatsapp' => 'WhatsApp',
            'call' => 'Appel',
            default => ucfirst($this->contact_type),
        };
    }

    /**
     * Obtenir l'icône du type de contact
     */
    public function getContactTypeIconAttribute(): string
    {
        return match($this->contact_type) {
            'whatsapp' => 'fa-whatsapp',
            'call' => 'fa-phone',
            default => 'fa-phone',
        };
    }

    /**
     * Obtenir la couleur du type de contact
     */
    public function getContactTypeColorAttribute(): string
    {
        return match($this->contact_type) {
            'whatsapp' => 'green',
            'call' => 'blue',
            default => 'gray',
        };
    }
}
