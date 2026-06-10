<?php

declare(strict_types=1);

namespace App\CryptoPayments\Infrastructure\Persistence\Models;

use App\CryptoPayments\Domain\Enums\CryptoAsset;
use App\CryptoPayments\Domain\Enums\CryptoDepositStatus;
use Illuminate\Database\Eloquent\Model;

final class CryptoDepositModel extends Model
{
    protected $table = 'crypto_deposits';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'payment_id',
        'status',
        'asset',
        'expected_units',
        'actual_units',
        'fiat_amount_kopecks',
        'deposit_address',
        'memo',
        'tx_hash',
        'expires_at',
        'created_at_ts',
    ];

    protected $casts = [
        'status' => CryptoDepositStatus::class,
        'asset' => CryptoAsset::class,
        'expected_units' => 'integer',
        'actual_units' => 'integer',
        'fiat_amount_kopecks' => 'integer',
        'created_at_ts' => 'integer',
    ];
}
