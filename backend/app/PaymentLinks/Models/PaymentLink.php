<?php

declare(strict_types=1);

namespace App\PaymentLinks\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

final class PaymentLink extends Model
{
    use HasUlids;

    protected $table = 'payment_links';

    protected $fillable = [
        'token',
        'amount',
        'currency',
        'description',
        'provider',
        'return_url',
        'metadata',
        'max_uses',
        'uses',
        'expires_at',
        'last_payment_id',
    ];

    protected $casts = [
        'metadata' => 'array',
        'expires_at' => 'datetime',
        'max_uses' => 'integer',
        'uses' => 'integer',
        'amount' => 'integer',
    ];

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isExhausted(): bool
    {
        return $this->uses >= $this->max_uses;
    }

    public function isActive(): bool
    {
        return ! $this->isExpired() && ! $this->isExhausted();
    }
}
