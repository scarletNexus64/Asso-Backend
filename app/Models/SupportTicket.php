<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SupportTicket extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'ticket_number',
        'subject',
        'message',
        'status',
        'priority',
        'category',
        'admin_id',
        'resolved_at',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
    ];

    /**
     * Boot du modèle
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($ticket) {
            if (empty($ticket->ticket_number)) {
                $ticket->ticket_number = 'TICKET-' . strtoupper(uniqid());
            }
        });
    }

    /**
     * Relations
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function replies()
    {
        return $this->hasMany(SupportReply::class);
    }

    /**
     * Scopes
     */
    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }

    public function scopeInProgress($query)
    {
        return $query->where('status', 'in_progress');
    }

    public function scopeResolved($query)
    {
        return $query->where('status', 'resolved');
    }

    public function scopeClosed($query)
    {
        return $query->where('status', 'closed');
    }

    public function scopeByPriority($query, $priority)
    {
        return $query->where('priority', $priority);
    }

    public function scopeByCategory($query, $category)
    {
        return $query->where('category', $category);
    }

    public function scopeRecent($query)
    {
        return $query->orderBy('created_at', 'desc');
    }

    /**
     * Attributs
     */
    public function getStatusLabelAttribute(): string
    {
        return match($this->status) {
            'open' => 'Ouvert',
            'in_progress' => 'En cours',
            'resolved' => 'Résolu',
            'closed' => 'Fermé',
            default => ucfirst($this->status),
        };
    }

    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            'open' => 'blue',
            'in_progress' => 'yellow',
            'resolved' => 'green',
            'closed' => 'gray',
            default => 'gray',
        };
    }

    public function getPriorityLabelAttribute(): string
    {
        return match($this->priority) {
            'low' => 'Basse',
            'medium' => 'Moyenne',
            'high' => 'Haute',
            'urgent' => 'Urgente',
            default => ucfirst($this->priority),
        };
    }

    public function getPriorityColorAttribute(): string
    {
        return match($this->priority) {
            'low' => 'gray',
            'medium' => 'blue',
            'high' => 'orange',
            'urgent' => 'red',
            default => 'gray',
        };
    }

    public function getCategoryLabelAttribute(): string
    {
        return match($this->category) {
            'technique' => 'Technique',
            'payment' => 'Paiement',
            'product' => 'Produit',
            'account' => 'Compte',
            'other' => 'Autre',
            default => ucfirst($this->category),
        };
    }

    public function getCategoryIconAttribute(): string
    {
        return match($this->category) {
            'technique' => 'fa-wrench',
            'payment' => 'fa-credit-card',
            'product' => 'fa-box',
            'account' => 'fa-user',
            'other' => 'fa-question-circle',
            default => 'fa-ticket-alt',
        };
    }
}
