<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AffiliateCommission extends Model
{
    use HasFactory;

    protected $fillable = [
        'affiliate_id',
        'referred_user_id',
        'transaction_id',
        'level',
        'amount',
        'percentage',
        'status',
        'description',
        'paid_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'percentage' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    /**
     * Relations
     */
    public function affiliate()
    {
        return $this->belongsTo(User::class, 'affiliate_id');
    }

    public function referredUser()
    {
        return $this->belongsTo(User::class, 'referred_user_id');
    }

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }

    /**
     * Scopes
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }

    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

    public function scopeByLevel($query, $level)
    {
        return $query->where('level', $level);
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
            'pending' => 'En attente',
            'approved' => 'Approuvée',
            'paid' => 'Payée',
            'rejected' => 'Rejetée',
            default => ucfirst($this->status),
        };
    }

    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            'pending' => 'yellow',
            'approved' => 'blue',
            'paid' => 'green',
            'rejected' => 'red',
            default => 'gray',
        };
    }

    public function getLevelLabelAttribute(): string
    {
        return 'Niveau ' . $this->level;
    }
}
