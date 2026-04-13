<?php

declare(strict_types=1);

namespace App\Payments\Infrastructure\Persistence\Models;

use App\Payments\Domain\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class PaymentModel extends Model
{
    use HasUlids;

    protected $table = 'payments';
    protected $fillable = [
        'id',
        'idempotency_key',
        'external_id',
        'payment_method_id',
        'provider',
        'amount',
        'refunded_amount',
        'currency',
        'description',
        'status',
        'confirmation_url',
        'metadata',
    ];

    protected $casts = [
        'status' => PaymentStatus::class,
        'metadata' => 'array',
    ];

    public function events(): HasMany
    {
        return $this->hasMany(PaymentEventModel::class, 'payment_id');
    }
}
