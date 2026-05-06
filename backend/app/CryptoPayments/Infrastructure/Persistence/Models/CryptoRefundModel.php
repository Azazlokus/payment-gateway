<?php

declare(strict_types=1);

namespace App\CryptoPayments\Infrastructure\Persistence\Models;

use App\CryptoPayments\Domain\Enums\CryptoAsset;
use App\CryptoPayments\Domain\Enums\CryptoRefundStatus;
use Illuminate\Database\Eloquent\Model;

final class CryptoRefundModel extends Model
{
    protected $table = 'crypto_refund_requests';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'deposit_id',
        'to_address',
        'amount_units',
        'asset',
        'status',
        'tx_hash',
        'failure_reason',
    ];

    protected $casts = [
        'status'       => CryptoRefundStatus::class,
        'asset'        => CryptoAsset::class,
        'amount_units' => 'integer',
    ];
}
