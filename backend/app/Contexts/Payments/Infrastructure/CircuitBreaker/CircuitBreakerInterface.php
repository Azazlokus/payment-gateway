<?php

declare(strict_types=1);

namespace App\Contexts\Payments\Infrastructure\CircuitBreaker;

interface CircuitBreakerInterface
{
    public function isAvailable(string $service): bool;

    public function recordSuccess(string $service): void;

    public function recordFailure(string $service): void;

    public function getState(string $service): CircuitState;

    public function retryAfterSeconds(string $service): int;

    public function reset(string $service): void;
}
