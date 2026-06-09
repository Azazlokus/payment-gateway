<?php

declare(strict_types=1);

namespace App\Payments\Infrastructure\CircuitBreaker;

use App\Payments\Domain\Exceptions\PaymentException;

class CircuitOpenException extends PaymentException
{
    public function __construct(
        public readonly string $provider,
        public readonly int $retryAfterSeconds,
    ) {
        parent::__construct(
            "Circuit breaker is open for provider '{$provider}', retry after {$retryAfterSeconds}s"
        );
    }
}
