<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class Credential extends Model
{
    protected $fillable = [
        'category_id',
        'name',
        'type',
        'username',
        'password',
        'url',
        'notes',
        'custom_fields',
        'is_favorite',
        'last_used_at',
    ];

    protected $casts = [
        'custom_fields' => 'array',
        'is_favorite' => 'boolean',
        'last_used_at' => 'datetime',
    ];

    protected $hidden = [
        'password', // Ne pas exposer le password dans les JSON
    ];

    /**
     * Chiffrer automatiquement le password avant de sauvegarder.
     */
    public function setPasswordAttribute($value)
    {
        $this->attributes['password'] = Crypt::encryptString($value);
    }

    /**
     * Déchiffrer automatiquement le password lors de la lecture.
     */
    public function getDecryptedPasswordAttribute()
    {
        try {
            return Crypt::decryptString($this->attributes['password']);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Relation avec la catégorie.
     */
    public function category()
    {
        return $this->belongsTo(CredentialCategory::class, 'category_id');
    }

    /**
     * Marquer comme utilisé récemment.
     */
    public function markAsUsed()
    {
        $this->update(['last_used_at' => now()]);
    }

    /**
     * Scope pour les favoris.
     */
    public function scopeFavorites($query)
    {
        return $query->where('is_favorite', true);
    }

    /**
     * Scope pour un type spécifique.
     */
    public function scopeOfType($query, $type)
    {
        return $query->where('type', $type);
    }
}
