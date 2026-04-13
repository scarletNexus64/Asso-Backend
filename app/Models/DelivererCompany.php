<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DelivererCompany extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'phone',
        'email',
        'description',
        'logo',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get the user/deliverer that owns this company
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get all delivery zones for this company
     */
    public function deliveryZones(): HasMany
    {
        return $this->hasMany(DeliveryZone::class);
    }

    /**
     * Get active delivery zones
     */
    public function activeDeliveryZones(): HasMany
    {
        return $this->hasMany(DeliveryZone::class)->where('is_active', true);
    }

    /**
     * Get all sync codes for this company
     */
    public function syncCodes(): HasMany
    {
        return $this->hasMany(DelivererSyncCode::class, 'company_id');
    }

    /**
     * Get all code syncs (users who synced) for this company
     */
    public function codeSyncs(): HasMany
    {
        return $this->hasMany(DelivererCodeSync::class, 'company_id');
    }

    /**
     * Get active code syncs for this company
     */
    public function activeCodeSyncs(): HasMany
    {
        return $this->hasMany(DelivererCodeSync::class, 'company_id')
            ->where('is_active', true)
            ->where('is_banned', false);
    }
}
