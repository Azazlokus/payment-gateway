<?php

declare(strict_types=1);

namespace App\Payments\Infrastructure\Persistence\Models;

use App\Payments\Domain\Enums\DisputeStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class DisputeModel extends Model
{
    use HasUlids;

    protected $table = 'disputes';

    protected $fillable = [
        'id',
        'payment_id',
        'status',
        'amount',
        'currency',
        'reason',
        'note',
    ];

    protected $casts = [
        'status' => DisputeStatus::class,
    ];

    /** @return BelongsTo<PaymentModel, $this> */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(PaymentModel::class, 'payment_id');
    }
}
