<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class VendorPackage extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'package_id',
        'storage_total_mb',
        'storage_used_mb',
        'storage_remaining_mb',
        'purchased_at',
        'expires_at',
        'status',
        'payment_reference',
        'custom_name',
    ];

    protected $casts = [
        'storage_total_mb' => 'decimal:2',
        'storage_used_mb' => 'decimal:2',
        'storage_remaining_mb' => 'decimal:2',
        'purchased_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    /**
     * Get the user that owns this vendor package
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the package that this vendor package is based on
     */
    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    /**
     * Check if the vendor package is active
     */
    public function isActive(): bool
    {
        return $this->status === 'active' && $this->expires_at > now();
    }

    /**
     * Check if vendor has enough storage space
     */
    public function hasEnoughStorage(float $sizeInMb): bool
    {
        return $this->storage_remaining_mb >= $sizeInMb;
    }

    /**
     * Deduct storage space from the vendor package
     */
    public function deductStorage(float $sizeInMb): void
    {
        DB::transaction(function () use ($sizeInMb) {
            $this->storage_used_mb += $sizeInMb;
            $this->storage_remaining_mb -= $sizeInMb;
            $this->save();
        });
    }

    /**
     * Add storage space back to the vendor package (when product is deleted)
     */
    public function addStorage(float $sizeInMb): void
    {
        DB::transaction(function () use ($sizeInMb) {
            $this->storage_used_mb = max(0, $this->storage_used_mb - $sizeInMb);
            $this->storage_remaining_mb = min($this->storage_total_mb, $this->storage_remaining_mb + $sizeInMb);
            $this->save();
        });
    }

    /**
     * Scope to get only active vendor packages
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active')
            ->where('expires_at', '>', now());
    }
}
