<?php

declare(strict_types=1);

namespace App\CryptoPayments\Domain\ValueObjects;

use App\CryptoPayments\Domain\Enums\CryptoAsset;
use InvalidArgumentException;

final readonly class NativeCryptoAmount
{
    private function __construct(
        private int $units,
        private CryptoAsset $asset,
    ) {}

    public static function ofNanotons(int $units): self
    {
        return new self($units, CryptoAsset::TON);
    }

    public static function ofMicroUsdt(int $units): self
    {
        return new self($units, CryptoAsset::USDT_TON);
    }

    public function units(): int
    {
        return $this->units;
    }

    public function asset(): CryptoAsset
    {
        return $this->asset;
    }

    /**
     * Returns a human-readable string representation.
     * TON: 9 decimal places (nanotons → TON)
     * USDT_TON: 6 decimal places (microUSDT → USDT)
     */
    public function humanReadable(): string
    {
        $decimals = $this->asset->decimals();
        $divisor  = 10 ** $decimals;

        $whole    = intdiv($this->units, $divisor);
        $fraction = abs($this->units % $divisor);

        return sprintf('%d.%0' . $decimals . 'd', $whole, $fraction);
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
