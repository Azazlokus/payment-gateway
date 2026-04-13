<?php

declare(strict_types=1);

namespace App\Payments\Infrastructure\Observability;

use Illuminate\Support\Facades\Log;

class PaymentLogger
{
    public function info(string $message, array $context = []): void
    {
        Log::channel('payments')->info($message, $this->enrich($context));
    }

    public function error(string $message, array $context = []): void
    {
        Log::channel('payments')->error($message, $this->enrich($context));
    }

    public function warning(string $message, array $context = []): void
    {
        Log::channel('payments')->warning($message, $this->enrich($context));
    }

    private function enrich(array $context): array
    {
        return array_merge([
            'service' => 'payment-gateway',
            'environment' => app()->environment(),
            'correlation_id' => request()->header('X-Correlation-Id'),
            'timestamp' => now()->toIso8601String(),
        ], $context);
    }
}
