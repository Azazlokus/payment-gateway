<?php

declare(strict_types=1);

namespace App\CryptoPayments\Domain\Contracts;

use App\CryptoPayments\Domain\Enums\CryptoAsset;

interface PriceOracleInterface
{
    /**
     * Returns how many RUB units (kopecks) equal 1 "human unit" of asset.
     * e.g., TON at 400 RUB → 40000 kopecks per TON.
     */
    public function getRateKopecks(CryptoAsset $asset): int;

    /**
     * Convert kopecks to smallest crypto units.
     * e.g., 5000 kopecks (50 RUB), TON at 400 RUB → 125_000_000 nanotons
     */
    public function kopecksToCryptoUnits(int $kopecks, CryptoAsset $asset): int;
}
