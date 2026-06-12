<?php

declare(strict_types=1);

namespace App\Contexts\CryptoPayments\Domain\ValueObjects;

use App\Contexts\CryptoPayments\Domain\Enums\CryptoAsset;
use InvalidArgumentException;

final readonly class NativeCryptoAmount
{
    private function __construct(
        private int $units,
        private CryptoAsset $asset,
    ) {}

    public static function of(int $units, CryptoAsset $asset): self
    {
        return new self($units, $asset);
    }

    public static function ofNanotons(int $units): self
    {
        return new self($units, CryptoAsset::TON);
    }

    public static function ofMicroUsdt(int $units): self
    {
        return new self($units, CryptoAsset::USDT_TON);
    }

    public static function ofSatoshis(int $units): self
    {
        return new self($units, CryptoAsset::BTC);
    }

    public static function ofSunUnits(int $units): self
    {
        return new self($units, CryptoAsset::TRX);
    }

    public static function ofMicroUsdtTrc20(int $units): self
    {
        return new self($units, CryptoAsset::USDT_TRC20);
    }

    public function units(): int
    {
        return $this->units;
    }

    public function asset(): CryptoAsset
    {
        return $this->asset;
    }

    public function humanReadable(): string
    {
        $decimals = $this->asset->decimals();
        $divisor = 10 ** $decimals;
        $whole = intdiv($this->units, $divisor);
        $fraction = abs($this->units % $divisor);

        return sprintf('%d.%0'.$decimals.'d', $whole, $fraction);
    }

    public function isGreaterThanOrEqualTo(self $other): bool
    {
        return $this->units >= $other->units;
    }

    public function add(self $other): self
    {
        if ($this->asset !== $other->asset) {
            throw new InvalidArgumentException(
                "Cannot add amounts of different assets: {$this->asset->value} and {$other->asset->value}"
            );
        }

        return new self($this->units + $other->units, $this->asset);
    }
}
