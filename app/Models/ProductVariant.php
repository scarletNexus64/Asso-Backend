<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductVariant extends Model
{
    protected $fillable = [
        'sku', 'attributes', 'price_adjustment', 'stock', 'weight', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'attributes' => 'array',
        'price_adjustment' => 'decimal:2',
        'stock' => 'integer',
        'weight' => 'decimal:3',
        'is_active' => 'boolean',
    ];

    /**
     * Poids à facturer pour cette déclinaison, en kg.
     *
     * Retombe sur le poids du produit quand la déclinaison n'en définit pas.
     */
    public function effectiveWeight(): ?float
    {
        return $this->weight !== null
            ? (float) $this->weight
            : ($this->product?->weight !== null ? (float) $this->product->weight : null);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
