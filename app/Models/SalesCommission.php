<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * P6 — Commission d'un commercial sur une souscription payée.
 * Les données de la vente sont figées à la création (voir migration).
 */
class SalesCommission extends Model
{
    public const STATUS_DUE = 'due';

    public const STATUS_PAID = 'paid';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_LABELS = [
        self::STATUS_DUE => 'À payer',
        self::STATUS_PAID => 'Payée',
        self::STATUS_CANCELLED => 'Annulée',
    ];

    protected $fillable = [
        'sales_agent_id',
        'package_subscription_id',
        'vendor_id',
        'package_id',
        'sales_code',
        'package_name',
        'package_type',
        'amount_paid_xaf',
        'payment_method',
        'transaction_reference',
        'sold_at',
        'rate',
        'commission_amount',
        'status',
        'payout_reference',
        'paid_at',
        'paid_by',
        'cancel_reason',
        'cancelled_at',
    ];

    protected $casts = [
        'amount_paid_xaf' => 'decimal:2',
        'rate' => 'decimal:2',
        'commission_amount' => 'decimal:2',
        'sold_at' => 'datetime',
        'paid_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function salesAgent(): BelongsTo
    {
        return $this->belongsTo(SalesAgent::class)->withTrashed();
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(PackageSubscription::class, 'package_subscription_id');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vendor_id');
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function paidBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    public function scopeDue(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_DUE);
    }

    public function scopePaid(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PAID);
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }
}
