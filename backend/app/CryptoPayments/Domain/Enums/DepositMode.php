<?php

declare(strict_types=1);

namespace App\CryptoPayments\Domain\Enums;

enum DepositMode
{
    /** Single master address + numeric memo (TON, USDT_TON) */
    case Memo;

    /** Unique address per deposit from a pre-configured pool (BTC, TRX, USDT_TRC20) */
    case UniqueAddress;
}
