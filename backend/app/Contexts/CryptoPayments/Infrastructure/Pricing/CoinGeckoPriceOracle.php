<?php

declare(strict_types=1);

namespace App\Contexts\CryptoPayments\Infrastructure\Pricing;

use App\Contexts\CryptoPayments\Domain\Contracts\PriceOracleInterface;
use App\Contexts\CryptoPayments\Domain\Enums\CryptoAsset;
use App\Contexts\Payments\Infrastructure\Observability\PaymentLogger;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final readonly class CoinGeckoPriceOracle implements PriceOracleInterface
{
    public function __construct(
        private PaymentLogger $logger,
    ) {}

    public function getRateKopecks(CryptoAsset $asset): int
    {
        $coinId = $asset->coinGeckoId();
        $cacheKey = "crypto_price_kopecks_{$coinId}";
        $ttl = (int) config('crypto.price_oracle.cache_ttl_seconds', 60);

        /** @var int $rate */
        $rate = Cache::remember($cacheKey, $ttl, fn (): int => $this->fetchRateKopecks($coinId));

        return $rate;
    }

    public function kopecksToCryptoUnits(int $kopecks, CryptoAsset $asset): int
    {
        $rateKopecks = $this->getRateKopecks($asset);

        if ($rateKopecks <= 0) {
            throw new RuntimeException("Invalid rate for asset {$asset->value}: {$rateKopecks}");
        }

        // formula: units = round(kopecks * 10^decimals / rateKopecks)
        // e.g., 5000 kopecks, TON at 40000 kopecks/TON, decimals=9:
        //   units = round(5000 * 10^9 / 40000) = round(125_000_000_000 / 40000) = 125_000_000 nanotons
        $decimals = $asset->decimals();
        $factor = 10 ** $decimals;

        return intval(round(($kopecks * $factor) / $rateKopecks));
    }

    private function fetchRateKopecks(string $coinId): int
    {
        $baseUrl = config('crypto.price_oracle.base_url', 'https://api.coingecko.com/api/v3');
        $response = Http::get("{$baseUrl}/simple/price", [
            'ids' => $coinId,
            'vs_currencies' => 'rub',
        ]);

        if (! $response->successful()) {
            $this->logger->warning('CoinGecko API error', [
                'status' => $response->status(),
                'coin_id' => $coinId,
            ]);

            throw new RuntimeException("CoinGecko API returned {$response->status()} for {$coinId}");
        }

        /** @var array<string, array<string, float|int>> $data */
        $data = $response->json();
        $price = $data[$coinId]['rub'] ?? null;

        if ($price === null) {
            throw new RuntimeException("CoinGecko response missing price for {$coinId}");
        }

        // Convert RUB to kopecks (1 RUB = 100 kopecks), keep as integer
        return intval(round((float) $price * 100));
    }
}
