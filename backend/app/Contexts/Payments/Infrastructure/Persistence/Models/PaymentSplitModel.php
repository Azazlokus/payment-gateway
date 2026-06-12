<?php

declare(strict_types=1);

namespace App\Contexts\Payments\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PaymentSplitModel extends Model
{
    use HasUlids;

    protected $table = 'payment_splits';

    protected $fillable = [
        'id',
        'payment_id',
        'account_id',
        'amount',
        'currency',
        'description',
    ];

    /** @return BelongsTo<PaymentModel, $this> */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(PaymentModel::class, 'payment_id');
    }
}
