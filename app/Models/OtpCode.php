<?php

namespace App\Models;

use Database\Factories\OtpCodeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OtpCode extends Model
{
    /** @use HasFactory<OtpCodeFactory> */
    use HasFactory;

    protected $fillable = [
        'email',
        'code',
        'attempts',
        'expires_at',
        'verified_at',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'verified_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }

    public function hasExceededAttempts(int $max = 5): bool
    {
        return $this->attempts >= $max;
    }
}
