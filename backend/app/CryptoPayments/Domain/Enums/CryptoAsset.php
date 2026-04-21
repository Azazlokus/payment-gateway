<?php

declare(strict_types=1);

namespace App\CryptoPayments\Domain\Enums;

enum CryptoAsset: string
{
    case TON        = 'TON';
    case USDT_TON   = 'USDT_TON';
    case BTC        = 'BTC';
    case TRX        = 'TRX';
    case USDT_TRC20 = 'USDT_TRC20';

    public function decimals(): int
    {
        return match ($this) {
            self::TON        => 9,
            self::USDT_TON   => 6,
            self::BTC        => 8,
            self::TRX        => 6,
            self::USDT_TRC20 => 6,
        };
    }

    public function coinGeckoId(): string
    {
        return match ($this) {
            self::TON        => 'the-open-network',
            self::USDT_TON   => 'tether',
            self::BTC        => 'bitcoin',
            self::TRX        => 'tron',
            self::USDT_TRC20 => 'tether',
        };
    }

    public function network(): string
    {
        return match ($this) {
            self::TON, self::USDT_TON   => 'ton',
            self::BTC                   => 'bitcoin',
            self::TRX, self::USDT_TRC20 => 'tron',
        };
    }

    public function depositMode(): DepositMode
    {
        return match ($this) {
            self::TON, self::USDT_TON => DepositMode::Memo,
            default                   => DepositMode::UniqueAddress,
        };
    }
}
