<?php

declare(strict_types=1);

namespace App\Contexts\Payments\Infrastructure\Antifraud;

use App\Contexts\Payments\Infrastructure\Observability\MetricsService;
use App\Contexts\Payments\Infrastructure\Observability\PaymentLogger;
use Illuminate\Support\Facades\Redis;

/**
 * Redis sorted-set sliding window velocity checker.
 *
 * Key schema: velocity:{dimension}:{key}:count
 *             velocity:{dimension}:{key}:amount
 *
 * Each entry in the sorted set: score = unix timestamp, member = unique event id.
 */
final class VelocityChecker
{
    private const string PREFIX = 'velocity:';

    /** @var VelocityRule[] */
    private array $rules = [];

    public function __construct(
        private readonly PaymentLogger $logger,
        private readonly MetricsService $metrics,
    ) {}

    public function addRule(VelocityRule $rule): void
    {
        $this->rules[] = $rule;
    }

    /** @param array<string, string|null> $dimensions e.g. ['ip' => '1.2.3.4', 'user_id' => '42'] */
    public function check(array $dimensions, int $amountKopecks): void
    {
        // Если Redis недоступен — пропускаем проверку: rate-limiting не должен
        // блокировать платежи при кратковременном падении Redis.
        try {
            $this->doCheck($dimensions, $amountKopecks);
        } catch (VelocityLimitExceededException $e) {
            throw $e; // Превышение лимита — пробрасываем
        } catch (\Throwable $e) {
            $this->logger->warning('VelocityChecker: Redis недоступен, проверка пропущена', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /** @param array<string, string|null> $dimensions */
    public function record(array $dimensions, int $amountKopecks, string $eventId): void
    {
        try {
            $this->doRecord($dimensions, $amountKopecks, $eventId);
        } catch (\Throwable $e) {
            $this->logger->warning('VelocityChecker: Redis недоступен, запись пропущена', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /** @param array<string, string|null> $dimensions */
    private function doCheck(array $dimensions, int $amountKopecks): void
    {
        $now = microtime(true);

        foreach ($this->rules as $rule) {
            $dimensionValue = $dimensions[$rule->dimension] ?? null;

            if ($dimensionValue === null || $dimensionValue === '') {
                continue;
            }

            $this->checkCountLimit($rule, $dimensionValue, $now);

            if ($rule->maxAmountKopecks !== null) {
                $this->checkAmountLimit($rule, $dimensionValue, $amountKopecks, $now);
            }
        }
    }

    /** @param array<string, string|null> $dimensions */
    private function doRecord(array $dimensions, int $amountKopecks, string $eventId): void
    {
        $now = microtime(true);

        foreach ($this->rules as $rule) {
            $dimensionValue = $dimensions[$rule->dimension] ?? null;

            if ($dimensionValue === null || $dimensionValue === '') {
                continue;
            }

            $countKey = $this->key($rule->dimension, $dimensionValue, 'count');
            Redis::zadd($countKey, $now, $eventId);
            Redis::expire($countKey, $rule->windowSeconds + 60);

            if ($rule->maxAmountKopecks !== null) {
                $amountKey = $this->key($rule->dimension, $dimensionValue, 'amount');
                Redis::zadd($amountKey, $now, $eventId.':'.$amountKopecks);
                Redis::expire($amountKey, $rule->windowSeconds + 60);
            }
        }
    }

    /** @return VelocityRule[] */
    public function rules(): array
    {
        return $this->rules;
    }

    private function checkCountLimit(VelocityRule $rule, string $dimensionValue, float $now): void
    {
        $countKey = $this->key($rule->dimension, $dimensionValue, 'count');
        $windowStart = $now - $rule->windowSeconds;

        // Remove expired entries
        Redis::zremrangebyscore($countKey, '-inf', (string) $windowStart);

        $count = (int) Redis::zcard($countKey);

        if ($count >= $rule->maxCount) {
            $this->logger->warning('Velocity count limit exceeded', [
                'dimension' => $rule->dimension,
                'key' => $dimensionValue,
                'count' => $count,
                'max' => $rule->maxCount,
                'window' => $rule->windowSeconds,
            ]);

            $this->metrics->increment('antifraud_velocity_rejected_total', [
                'dimension' => $rule->dimension,
                'reason' => 'count',
            ]);

            throw new VelocityLimitExceededException(
                dimension: $rule->dimension,
                key: $dimensionValue,
                rule: "max {$rule->maxCount} per {$rule->windowSeconds}s",
            );
        }
    }

    private function checkAmountLimit(VelocityRule $rule, string $dimensionValue, int $pendingAmount, float $now): void
    {
        $amountKey = $this->key($rule->dimension, $dimensionValue, 'amount');
        $windowStart = $now - $rule->windowSeconds;

        Redis::zremrangebyscore($amountKey, '-inf', (string) $windowStart);

        /** @var list<string> $entries */
        $entries = Redis::zrangebyscore($amountKey, (string) $windowStart, '+inf');
        $totalAmount = 0;

        foreach ($entries as $entry) {
            $parts = explode(':', (string) $entry);
            $totalAmount += (int) end($parts);
        }

        if (($totalAmount + $pendingAmount) > $rule->maxAmountKopecks) {
            $this->logger->warning('Velocity amount limit exceeded', [
                'dimension' => $rule->dimension,
                'key' => $dimensionValue,
                'total_amount' => $totalAmount,
                'pending_amount' => $pendingAmount,
                'max_amount' => $rule->maxAmountKopecks,
                'window' => $rule->windowSeconds,
            ]);

            $this->metrics->increment('antifraud_velocity_rejected_total', [
                'dimension' => $rule->dimension,
                'reason' => 'amount',
            ]);

            throw new VelocityLimitExceededException(
                dimension: $rule->dimension,
                key: $dimensionValue,
                rule: "max {$rule->maxAmountKopecks} kopecks per {$rule->windowSeconds}s",
            );
        }
    }

    private function key(string $dimension, string $value, string $suffix): string
    {
        return self::PREFIX.$dimension.':'.$value.':'.$suffix;
    }
}
