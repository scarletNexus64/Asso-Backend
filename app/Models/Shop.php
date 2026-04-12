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
        'categories',
        'logo',
        'shop_link',
        'address',
        'phone',
        'email',
        'latitude',
        'longitude',
        'status',
        'verified_at',
        'verified_by',
        'rejection_reason',
        'rejected_at',
        'rejected_by',
        'is_certified',
        'certified_at',
        'certification_expires_at',
        'certified_by',
    ];

    protected $casts = [
        'categories' => 'array',
        'verified_at' => 'datetime',
        'rejected_at' => 'datetime',
        'is_certified' => 'boolean',
        'certified_at' => 'datetime',
        'certification_expires_at' => 'datetime',
    ];

    /**
     * Attributes to append to model's array form
     */
    protected $appends = [
        'logo_url',
    ];

    /**
     * Get the logo URL attribute
     */
    public function getLogoUrlAttribute(): ?string
    {
        if (!$this->logo) {
            return null;
        }

        // If logo starts with http, it's already a full URL
        if (str_starts_with($this->logo, 'http')) {
            return $this->logo;
        }

        // Generate storage URL
        return \Storage::url($this->logo);
    }

    /**
     * Boot method to auto-generate slug
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($shop) {
            if (empty($shop->slug)) {
                $shop->slug = \Str::slug($shop->name) . '-' . \Str::random(6);
            }
        });
    }

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

    /**
     * Scope a query to only include pending shops (not verified, not rejected)
     */
    public function scopePending($query)
    {
        return $query->whereNull('verified_at')->whereNull('rejected_at');
    }

    /**
     * Scope a query to only include verified shops
     */
    public function scopeVerified($query)
    {
        return $query->whereNotNull('verified_at');
    }

    /**
     * Scope a query to only include rejected shops
     */
    public function scopeRejected($query)
    {
        return $query->whereNotNull('rejected_at');
    }

    /**
     * Check if shop is pending verification
     */
    public function isPending(): bool
    {
        return is_null($this->verified_at) && is_null($this->rejected_at);
    }

    /**
     * Check if shop is verified
     */
    public function isVerified(): bool
    {
        return !is_null($this->verified_at);
    }

    /**
     * Check if shop is rejected
     */
    public function isRejected(): bool
    {
        return !is_null($this->rejected_at);
    }

    /**
     * Get the verifier who approved the shop
     */
    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    /**
     * Get the admin who rejected the shop
     */
    public function rejector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    /**
     * Check if shop is certified
     */
    public function isCertified(): bool
    {
        if (!$this->is_certified) {
            return false;
        }

        // Check if certification has expired
        if ($this->certification_expires_at && $this->certification_expires_at->isPast()) {
            return false;
        }

        return true;
    }

    /**
     * Check if certification is expiring soon (within 30 days)
     */
    public function isCertificationExpiringSoon(): bool
    {
        if (!$this->is_certified || !$this->certification_expires_at) {
            return false;
        }

        return $this->certification_expires_at->diffInDays(now()) <= 30;
    }

    /**
     * Get days until certification expires
     */
    public function daysUntilCertificationExpiry(): ?int
    {
        if (!$this->is_certified || !$this->certification_expires_at) {
            return null;
        }

        return (int) $this->certification_expires_at->diffInDays(now());
    }

    /**
     * Get the admin who certified the shop
     */
    public function certifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'certified_by');
    }

    /**
     * Scope a query to only include certified shops
     */
    public function scopeCertified($query)
    {
        return $query->where('is_certified', true)
            ->where(function ($q) {
                $q->whereNull('certification_expires_at')
                  ->orWhere('certification_expires_at', '>', now());
            });
    }
}
