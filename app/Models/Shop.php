<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shop extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'slug',
        'description',
        'logo',
        'shop_link',
        'address',
        'latitude',
        'longitude',
        'status',
    ];

    /**
     * Get the user that owns the shop
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get all products for this shop
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
