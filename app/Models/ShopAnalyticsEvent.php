<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShopAnalyticsEvent extends Model
{
    public const SHOP_VIEW = 'shop_view';
    public const PRODUCT_VIEW = 'product_view';
    public const CONTACT = 'contact';

    public const TYPES = [self::SHOP_VIEW, self::PRODUCT_VIEW, self::CONTACT];

    public const UPDATED_AT = null;

    protected $fillable = [
        'shop_id', 'product_id', 'user_id', 'event_type', 'visitor_hash', 'source', 'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
