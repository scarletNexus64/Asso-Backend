<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Asso Ads — consommation d'une campagne pour une journée donnée. */
class ProductBoostDailyStat extends Model
{
    protected $fillable = ['product_boost_id', 'date', 'impressions', 'clicks'];

    protected $casts = [
        'date' => 'date',
        'impressions' => 'integer',
        'clicks' => 'integer',
    ];

    public function boost(): BelongsTo
    {
        return $this->belongsTo(ProductBoost::class, 'product_boost_id');
    }
}
