<?php

namespace App\Models;

use App\Support\AssoReferenceCode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssoProductReference extends Model
{
    protected $fillable = ['name', 'address', 'city', 'country', 'quarter', 'latitude', 'longitude', 'is_active'];

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function isLocated(): bool
    {
        return Shop::isPlaced($this->latitude, $this->longitude, $this->address)
            && filled($this->city) && filled($this->country);
    }

    /** Lieu lisible, sans séparateur orphelin quand une partie manque. */
    public function placeLabel(): string
    {
        return implode(', ', array_filter([$this->quarter, $this->city]));
    }

    /** Libellé de liste déroulante : « CODE · Quartier, Ville ». */
    public function optionLabel(): string
    {
        return implode(' · ', array_filter([$this->name, $this->placeLabel(), $this->is_active ? null : 'désactivée']));
    }

    /** Recherche insensible à la casse et aux espaces du code. */
    public function scopeNamed(Builder $query, string $name): Builder
    {
        return $query->whereRaw('upper(name) = ?', [AssoReferenceCode::normalize($name)]);
    }

    protected function casts(): array
    {
        return ['latitude' => 'decimal:8', 'longitude' => 'decimal:8', 'is_active' => 'boolean'];
    }
}
