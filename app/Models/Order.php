<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $fillable = [
        'order_number', 'user_id', 'status', 'subtotal', 'delivery_fee', 'total',
        'delivery_address', 'delivery_latitude', 'delivery_longitude',
        'tracking_number', 'delivery_person_id', 'payment_method',
        'payment_reference', 'payment_status', 'notes', 'cancel_reason',
        'confirmed_at', 'shipped_at', 'delivered_at', 'cancelled_at',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'delivery_fee' => 'decimal:2',
        'total' => 'decimal:2',
        'confirmed_at' => 'datetime',
        'shipped_at' => 'datetime',
        'delivered_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($order) {
            if (empty($order->order_number)) {
                $order->order_number = 'CMD' . str_pad(random_int(1, 999999), 6, '0', STR_PAD_LEFT);
            }
            if (empty($order->tracking_number)) {
                $order->tracking_number = 'TRK' . strtoupper(substr(md5(uniqid()), 0, 10));
            }
        });
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function deliveryPerson(): BelongsTo { return $this->belongsTo(User::class, 'delivery_person_id'); }
    public function items(): HasMany { return $this->hasMany(OrderItem::class); }

    public function getFormattedTotalAttribute(): string
    {
        return number_format($this->total, 0, ',', ' ') . ' FCFA';
    }
}
