<?php

namespace App\Models\RealEstate;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Morceaux d'un terrain achetés par une réservation (lot × quantité). */
class ReservationLot extends Model
{
    protected $table = 'real_estate_reservation_lots';

    protected $fillable = ['reservation_id', 'unit_id', 'label', 'quantity', 'unit_price'];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'integer',
    ];

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function toApi(): array
    {
        return [
            'unit_id' => $this->unit_id,
            'label' => $this->label,
            'quantity' => $this->quantity,
            'unit_price' => $this->unit_price,
        ];
    }
}
