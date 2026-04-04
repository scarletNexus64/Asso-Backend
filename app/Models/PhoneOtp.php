<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PhoneOtp extends Model
{
    protected $fillable = ['phone', 'code', 'expires_at', 'verified'];

    protected $casts = [
        'expires_at' => 'datetime',
        'verified' => 'boolean',
    ];

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isValidCode(string $code): bool
    {
        return !$this->isExpired() && !$this->verified && $this->code === $code;
    }
}
