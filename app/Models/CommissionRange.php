<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommissionRange extends Model
{
    protected $fillable = [
        'min_amount',
        'max_amount',
        'percentage',
        'is_active',
    ];

    protected $casts = [
        'min_amount' => 'decimal:2',
        'max_amount' => 'decimal:2',
        'percentage' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public static function getCommissionForAmount(float $amount): ?float
    {
        $range = self::where('is_active', true)
            ->where('min_amount', '<=', $amount)
            ->where('max_amount', '>=', $amount)
            ->first();

        return $range ? (float) $range->percentage : null;
    }
}
