<?php

declare(strict_types=1);

namespace App\Payments\Infrastructure\Observability;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

/**
 * Хранит метрики в Redis и отдаёт в формате Prometheus text exposition.
 *
 * Ключи Redis: metrics:{metric_name}:{label1=val1}:{label2=val2}
 */
class MetricsService
{
    private const PREFIX = 'metrics:';

    // ─── Счётчики ─────────────────────────────────────────────────────────────

    /** @param array<string, string> $labels */
    public function increment(string $name, array $labels = []): void
    {
        Redis::incr($this->key($name, $labels));
    }

    /** @param array<string, string> $labels */
    public function add(string $name, int $value, array $labels = []): void
    {
        if ($value > 0) {
            Redis::incrby($this->key($name, $labels), $value);
        }
    }

    // ─── Шорткаты для домена ─────────────────────────────────────────────────

    public function paymentCreated(string $provider): void
    {
        $this->increment('payments_created_total', ['provider' => $provider]);
    }

    public function paymentSucceeded(string $provider): void
    {
        $this->increment('payments_succeeded_total', ['provider' => $provider]);
    }

    public function paymentCancelled(string $provider): void
    {
        $this->increment('payments_cancelled_total', ['provider' => $provider]);
    }

    public function paymentRefunded(string $provider, int $amountKopecks): void
    {
        $this->increment('payments_refunded_total', ['provider' => $provider]);
        $this->add('payments_refunded_amount_kopecks_total', $amountKopecks, ['provider' => $provider]);
    }

    public function paymentAmount(string $provider, string $currency, int $amountKopecks): void
    {
        $this->add('payments_amount_kopecks_total', $amountKopecks, [
            'provider' => $provider,
            'currency' => $currency,
        ]);
    }

    public function webhookProcessed(string $provider, string $event): void
    {
        $this->increment('webhooks_processed_total', [
            'provider' => $provider,
            'event' => $event,
        ]);
    }

    public function webhookFailed(string $provider): void
    {
        $this->increment('webhooks_failed_total', ['provider' => $provider]);
    }

    public function notificationSent(bool $success): void
    {
        $this->increment('outbound_notifications_total', ['status' => $success ? 'success' : 'failed']);
    }

    public function throttleRejected(string $endpoint): void
    {
        $this->increment('throttle_rejections_total', ['endpoint' => $endpoint]);
    }

    public function disputeFiled(string $provider): void
    {
        $this->increment('disputes_filed_total', ['provider' => $provider]);
    }

    public function disputeResolved(string $resolution): void
    {
        $this->increment('disputes_resolved_total', ['resolution' => $resolution]);
    }

    public function circuitBreakerStateChanged(string $provider, string $state): void
    {
        $this->increment('circuit_breaker_state_changes_total', [
            'provider' => $provider,
            'state' => $state,
        ]);
    }

    // ─── Сериализация в Prometheus text format ────────────────────────────────

    public function dump(): string
    {
        $keys = Redis::keys(self::PREFIX.'*');

        if (empty($keys)) {
            return "# No metrics yet\n";
        }

        $metrics = [];

        foreach ($keys as $redisKey) {
            // Убираем возможный префикс Redis (при использовании Redis::connection с префиксом)
            $key = preg_replace('/^[^:]*:'.preg_quote(self::PREFIX, '/').'/', self::PREFIX, $redisKey);
            $value = Redis::get($redisKey);

            if ($value === null) {
                continue;
            }

            $parsed = $this->parseKey((string) $key);
            $metrics[$parsed['name']][] = [
                'labels' => $parsed['labels'],
                'value' => $value,
            ];
        }

        $output = '';

        foreach ($metrics as $name => $series) {
            $output .= "# TYPE {$name} counter\n";

            foreach ($series as $entry) {
                $labelStr = $this->formatLabels($entry['labels']);
                $output .= "{$name}{$labelStr} {$entry['value']}\n";
            }
        }

        // DLQ gauge — читаем из БД при каждом dump()
        $failedJobsCount = DB::table('failed_jobs')->count();
        $output .= "# TYPE failed_jobs_count gauge\n";
        $output .= "failed_jobs_count {$failedJobsCount}\n";

        return $output;
    }

    // ─── Private ─────────────────────────────────────────────────────────────

    /** @param array<string, string> $labels */
    private function key(string $name, array $labels): string
    {
        $key = self::PREFIX.$name;

        foreach ($labels as $k => $v) {
            $key .= ":{$k}={$v}";
        }

        return $key;
    }

    /**
     * @return array{name: string, labels: array<string, string>}
     */
    private function parseKey(string $key): array
    {
        $key = substr($key, strlen(self::PREFIX));
        $parts = explode(':', $key);
        $name = array_shift($parts);
        $labels = [];

        foreach ($parts as $part) {
            if (str_contains($part, '=')) {
                [$k, $v] = explode('=', $part, 2);
                $labels[$k] = $v;
            }
        }

        return ['name' => $name, 'labels' => $labels];
    }

    /** @param array<string, string> $labels */
    private function formatLabels(array $labels): string
    {
        if (empty($labels)) {
            return '';
        }

        $parts = [];

        foreach ($labels as $k => $v) {
            $parts[] = "{$k}=\"{$v}\"";
        }

        return '{'.implode(',', $parts).'}';
    }
}
