<?php

declare(strict_types=1);

namespace App\Contexts\Payments\Infrastructure\CircuitBreaker;

use Illuminate\Support\Facades\Redis;

/**
 * Redis-backed circuit breaker.
 *
 * Keys per service:
 *   cb:{service}:failures   — failure counter (int)
 *   cb:{service}:state      — current state (closed|open|half_open)
 *   cb:{service}:opened_at  — timestamp when circuit opened
 */
final class CircuitBreaker implements CircuitBreakerInterface
{
    private const PREFIX = 'cb:';

    public function __construct(
        private readonly int $failureThreshold = 5,
        private readonly int $recoveryTimeoutSeconds = 30,
    ) {}

    // При недоступном Redis circuit breaker работает в fail-open режиме:
    // считаем сервис доступным, чтобы не блокировать платежи из-за Redis.

    public function isAvailable(string $service): bool
    {
        try {
            return $this->doIsAvailable($service);
        } catch (\Throwable) {
            return true; // fail-open
        }
    }

    public function recordSuccess(string $service): void
    {
        try {
            $state = $this->getState($service);

            if ($state === CircuitState::HalfOpen) {
                $this->reset($service);
            } elseif ($state === CircuitState::Closed) {
                Redis::del($this->key($service, 'failures'));
            }
        } catch (\Throwable) {
            // Redis недоступен — пропускаем
        }
    }

    public function recordFailure(string $service): void
    {
        try {
            $state = $this->getState($service);

            if ($state === CircuitState::HalfOpen) {
                $this->trip($service);

                return;
            }

            /** @var int $failures */
            $failures = Redis::incr($this->key($service, 'failures'));

            if ($failures >= $this->failureThreshold) {
                $this->trip($service);
            }
        } catch (\Throwable) {
            // Redis недоступен — пропускаем
        }
    }

    public function getState(string $service): CircuitState
    {
        $raw = Redis::get($this->key($service, 'state'));

        if ($raw === null) {
            return CircuitState::Closed;
        }

        return CircuitState::tryFrom((string) $raw) ?? CircuitState::Closed;
    }

    public function retryAfterSeconds(string $service): int
    {
        try {
            $openedAt = (int) Redis::get($this->key($service, 'opened_at'));
        } catch (\Throwable) {
            return 0;
        }

        if ($openedAt === 0) {
            return 0;
        }

        $elapsed = time() - $openedAt;
        $remaining = $this->recoveryTimeoutSeconds - $elapsed;

        return max(0, $remaining);
    }

    private function doIsAvailable(string $service): bool
    {
        $state = $this->getState($service);

        if ($state === CircuitState::Closed) {
            return true;
        }

        if ($state === CircuitState::Open) {
            if ($this->shouldAttemptRecovery($service)) {
                $this->transitionTo($service, CircuitState::HalfOpen);

                return true;
            }

            return false;
        }

        // HalfOpen — allow limited attempts
        return true;
    }

    public function reset(string $service): void
    {
        Redis::del(
            $this->key($service, 'failures'),
            $this->key($service, 'state'),
            $this->key($service, 'opened_at'),
        );
    }

    public function failureThreshold(): int
    {
        return $this->failureThreshold;
    }

    public function recoveryTimeoutSeconds(): int
    {
        return $this->recoveryTimeoutSeconds;
    }

    private function trip(string $service): void
    {
        $this->transitionTo($service, CircuitState::Open);
        Redis::set($this->key($service, 'opened_at'), (string) time());
        Redis::del($this->key($service, 'failures'));
    }

    private function shouldAttemptRecovery(string $service): bool
    {
        $openedAt = (int) Redis::get($this->key($service, 'opened_at'));

        return $openedAt > 0 && (time() - $openedAt) >= $this->recoveryTimeoutSeconds;
    }

    private function transitionTo(string $service, CircuitState $state): void
    {
        Redis::set($this->key($service, 'state'), $state->value);
    }

    private function key(string $service, string $suffix): string
    {
        return self::PREFIX.$service.':'.$suffix;
    }
}
