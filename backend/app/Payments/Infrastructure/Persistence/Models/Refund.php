<?php

declare(strict_types=1);

namespace App\Payments\Infrastructure\Persistence\Models;

use App\Payments\Domain\Enums\RefundStatus;
use Database\Factories\RefundFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

final class Refund extends Model
{
    /** @use HasFactory<RefundFactory> */
    use HasFactory;

    use HasUlids;

    protected static function newFactory(): RefundFactory
    {
        return RefundFactory::new();
    }

    protected $table = 'refunds';

    protected $fillable = [
        'payment_id',
        'external_id',
        'amount',
        'currency',
        'reason',
        'status',
        'idempotency_key',
        'attempts',
        'last_error',
        'next_retry_at',
    ];

    protected $casts = [
        'amount' => 'integer',
        'status' => RefundStatus::class,
        'attempts' => 'integer',
        'next_retry_at' => 'datetime',
    ];

    public function isRetryable(): bool
    {
        return in_array($this->status, [RefundStatus::Pending, RefundStatus::Failed], true)
            && $this->attempts < 5;
    }
}
