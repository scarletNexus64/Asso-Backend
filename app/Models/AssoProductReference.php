<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssoProductReference extends Model
{
    protected $fillable = ['name', 'address', 'city', 'country', 'quarter', 'latitude', 'longitude', 'is_active'];

    public function isLocated(): bool
    {
        return Shop::isPlaced($this->latitude, $this->longitude, $this->address)
            && filled($this->city) && filled($this->country);
    }

    protected function casts(): array
    {
        return ['latitude' => 'decimal:8', 'longitude' => 'decimal:8', 'is_active' => 'boolean'];
    }
}
