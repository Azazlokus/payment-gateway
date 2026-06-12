<?php

declare(strict_types=1);

namespace App\Contexts\CryptoPayments\Infrastructure\Observability;

use App\Contexts\Payments\Infrastructure\Observability\MetricsService;

final class CryptoMetricsService
{
    public function __construct(
        private readonly MetricsService $metrics,
    ) {}

    public function depositCreated(string $asset): void
    {
        $this->metrics->increment('crypto_deposits_created_total', ['asset' => $asset]);
    }

    public function depositConfirmed(string $asset): void
    {
        $this->metrics->increment('crypto_deposits_confirmed_total', ['asset' => $asset]);
    }

    public function depositExpired(string $asset): void
    {
        $this->metrics->increment('crypto_deposits_expired_total', ['asset' => $asset]);
    }

    public function depositOverpaid(string $asset): void
    {
        $this->metrics->increment('crypto_deposits_overpaid_total', ['asset' => $asset]);
    }
}
