<?php

declare(strict_types=1);

namespace App\Contexts\CryptoPayments\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CryptoDepositEventModel extends Model
{
    public $timestamps = false;

    protected $table = 'crypto_deposit_events';

    protected $primaryKey = 'event_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'deposit_id',
        'event_id',
        'event_name',
        'event_data',
        'occurred_at',
    ];

    protected $casts = [
        'event_data' => 'array',
        'occurred_at' => 'datetime',
    ];

    /** @return BelongsTo<CryptoDepositModel, $this> */
    public function deposit(): BelongsTo
    {
        return $this->belongsTo(CryptoDepositModel::class, 'deposit_id');
    }
}
