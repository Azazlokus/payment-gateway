<?php

declare(strict_types=1);

namespace App\Payments\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

final class Refund extends Model
{
    use HasUlids;

    protected $table = 'refunds';

    protected $fillable = [
        'payment_id',
        'external_id',
        'amount',
        'currency',
        'reason',
        'status',
        'idempotency_key',
    ];

    protected $casts = [
        'amount' => 'integer',
    ];
}
