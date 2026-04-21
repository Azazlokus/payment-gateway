<?php

declare(strict_types=1);

namespace App\CryptoPayments\Domain\Enums;

enum CryptoAsset: string
{
    case TON      = 'TON';
    case USDT_TON = 'USDT_TON';

    public function decimals(): int
    {
        return match ($this) {
            self::TON      => 9,
            self::USDT_TON => 6,
        };
    }

    public function coinGeckoId(): string
    {
        return match ($this) {
            self::TON      => 'the-open-network',
            self::USDT_TON => 'tether',
        };
    }

    public function network(): string
    {
        return 'ton';
    }
}
