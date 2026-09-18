<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id', 'product_id', 'product_variant_id', 'variant_attributes', 'seller_id', 'quantity', 'unit_price', 'total_price',
        'price_tier_id', 'tier_label',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'total_price' => 'decimal:2',
        'variant_attributes' => 'array',
    ];

    public function order(): BelongsTo { return $this->belongsTo(Order::class); }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
    public function variant(): BelongsTo { return $this->belongsTo(ProductVariant::class, 'product_variant_id'); }
    public function seller(): BelongsTo { return $this->belongsTo(User::class, 'seller_id'); }

    /** Restore both the aggregate product stock and its selected variant stock. */
    public function restoreStock(): void
    {
        if ($this->product && $this->product->stock !== null) {
            $this->product->increment('stock', $this->quantity);
        }
        if ($this->product_variant_id && $this->variant) {
            $this->variant->increment('stock', $this->quantity);
        }
    }
}
