<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    use HasFactory;

    protected $fillable = [
        'user1_id',
        'user2_id',
        'product_id',
        'last_message_at',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
    ];

    /**
     * Premier utilisateur
     */
    public function user1(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user1_id');
    }

    /**
     * Deuxième utilisateur
     */
    public function user2(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user2_id');
    }

    /**
     * Produit concerné
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Messages de la conversation
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /**
     * Dernier message
     */
    public function latestMessage()
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }

    /**
     * Nombre de messages non lus
     */
    public function unreadCount(): int
    {
        return $this->messages()->where('is_read', false)->count();
    }

    /**
     * Obtenir l'autre participant de la conversation
     */
    public function getOtherUser($currentUserId)
    {
        return $this->user1_id == $currentUserId ? $this->user2 : $this->user1;
    }

    /**
     * Scope pour les conversations récentes
     */
    public function scopeRecent($query)
    {
        return $query->orderBy('last_message_at', 'desc');
    }

    /**
     * Scope pour les conversations avec messages non lus
     */
    public function scopeWithUnread($query)
    {
        return $query->whereHas('messages', function($q) {
            $q->where('is_read', false);
        });
    }
}
