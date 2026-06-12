<?php

declare(strict_types=1);

namespace App\Contexts\Payments\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PaymentMethodEventModel extends Model
{
    public $timestamps = false;

    protected $table = 'payment_method_events';

    protected $primaryKey = 'event_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'payment_method_id',
        'event_id',
        'event_name',
        'event_data',
        'occurred_at',
    ];

    protected $casts = [
        'event_data' => 'array',
        'occurred_at' => 'datetime',
    ];

    /** @return BelongsTo<PaymentMethodModel, $this> */
    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethodModel::class, 'payment_method_id');
    }
}
