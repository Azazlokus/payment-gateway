<?php

declare(strict_types=1);

namespace Tests\Feature\Observability;

use App\Contexts\Payments\Infrastructure\Observability\MetricsService;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MetricsServiceTest extends TestCase
{
    private MetricsService $metrics;

    protected function setUp(): void
    {
        parent::setUp();

        try {
            Redis::ping();
        } catch (\Throwable) {
            $this->markTestSkipped('Redis is not available in this environment.');
        }

        // dump() читает failed_jobs — создаём таблицу если её нет (нет миграции)
        if (! Schema::hasTable('failed_jobs')) {
            Schema::create('failed_jobs', function ($table) {
                $table->id();
                $table->text('uuid')->unique();
                $table->text('connection');
                $table->text('queue');
                $table->longText('payload');
                $table->longText('exception');
                $table->timestamp('failed_at')->useCurrent();
            });
        }

        $this->metrics = new MetricsService;
        // Очищаем метрики перед каждым тестом
        $keys = Redis::keys('metrics:*');
        if (! empty($keys)) {
            Redis::del($keys);
        }
    }

    public function test_increment_stores_value_in_redis(): void
    {
        $this->metrics->increment('test_counter', ['env' => 'test']);

        $value = Redis::get('metrics:test_counter:env=test');
        $this->assertSame('1', $value);
    }

    public function test_increment_accumulates_values(): void
    {
        $this->metrics->increment('test_counter', ['env' => 'test']);
        $this->metrics->increment('test_counter', ['env' => 'test']);
        $this->metrics->increment('test_counter', ['env' => 'test']);

        $value = Redis::get('metrics:test_counter:env=test');
        $this->assertSame('3', $value);
    }

    public function test_add_stores_value(): void
    {
        $this->metrics->add('payment_amount', 50000, ['provider' => 'yookassa']);

        $value = Redis::get('metrics:payment_amount:provider=yookassa');
        $this->assertSame('50000', $value);
    }

    public function test_add_accumulates_values(): void
    {
        $this->metrics->add('payment_amount', 30000, ['provider' => 'robokassa']);
        $this->metrics->add('payment_amount', 20000, ['provider' => 'robokassa']);

        $value = Redis::get('metrics:payment_amount:provider=robokassa');
        $this->assertSame('50000', $value);
    }

    public function test_add_ignores_zero_or_negative_value(): void
    {
        $this->metrics->add('payment_amount', 0, ['provider' => 'sbp']);
        $this->metrics->add('payment_amount', -100, ['provider' => 'sbp']);

        $value = Redis::get('metrics:payment_amount:provider=sbp');
        $this->assertNull($value);
    }

    public function test_payment_created_shortcut(): void
    {
        $this->metrics->paymentCreated('cloudpayments');

        $value = Redis::get('metrics:payments_created_total:provider=cloudpayments');
        $this->assertSame('1', $value);
    }

    public function test_payment_succeeded_shortcut(): void
    {
        $this->metrics->paymentSucceeded('alfabank');

        $value = Redis::get('metrics:payments_succeeded_total:provider=alfabank');
        $this->assertSame('1', $value);
    }

    public function test_payment_cancelled_shortcut(): void
    {
        $this->metrics->paymentCancelled('sbp');

        $value = Redis::get('metrics:payments_cancelled_total:provider=sbp');
        $this->assertSame('1', $value);
    }

    public function test_payment_refunded_shortcut_increments_count_and_amount(): void
    {
        $this->metrics->paymentRefunded('yookassa', 75000);

        $this->assertSame('1', Redis::get('metrics:payments_refunded_total:provider=yookassa'));
        $this->assertSame('75000', Redis::get('metrics:payments_refunded_amount_kopecks_total:provider=yookassa'));
    }

    public function test_webhook_processed_shortcut(): void
    {
        $this->metrics->webhookProcessed('cloudpayments', 'payment.succeeded');

        $value = Redis::get('metrics:webhooks_processed_total:provider=cloudpayments:event=payment.succeeded');
        $this->assertSame('1', $value);
    }

    public function test_webhook_failed_shortcut(): void
    {
        $this->metrics->webhookFailed('robokassa');

        $value = Redis::get('metrics:webhooks_failed_total:provider=robokassa');
        $this->assertSame('1', $value);
    }

    public function test_notification_sent_shortcut_success(): void
    {
        $this->metrics->notificationSent(true);

        $value = Redis::get('metrics:outbound_notifications_total:status=success');
        $this->assertSame('1', $value);
    }

    public function test_notification_sent_shortcut_failure(): void
    {
        $this->metrics->notificationSent(false);

        $value = Redis::get('metrics:outbound_notifications_total:status=failed');
        $this->assertSame('1', $value);
    }

    public function test_dump_returns_no_metrics_message_when_empty(): void
    {
        $output = $this->metrics->dump();

        $this->assertStringContainsString('No metrics yet', $output);
    }

    public function test_dump_returns_prometheus_format(): void
    {
        $this->metrics->paymentCreated('yookassa');
        $this->metrics->paymentCreated('robokassa');
        $this->metrics->paymentSucceeded('yookassa');

        $output = $this->metrics->dump();

        $this->assertStringContainsString('# TYPE payments_created_total counter', $output);
        $this->assertStringContainsString('payments_created_total{provider="yookassa"} 1', $output);
        $this->assertStringContainsString('payments_created_total{provider="robokassa"} 1', $output);
        $this->assertStringContainsString('# TYPE payments_succeeded_total counter', $output);
        $this->assertStringContainsString('payments_succeeded_total{provider="yookassa"} 1', $output);
    }

    public function test_dump_handles_labels_correctly_in_key(): void
    {
        $this->metrics->paymentAmount('alfabank', 'RUB', 100000);

        $output = $this->metrics->dump();

        $this->assertStringContainsString('payments_amount_kopecks_total{provider="alfabank",currency="RUB"} 100000', $output);
    }

    public function test_increment_without_labels_stores_simple_key(): void
    {
        $this->metrics->increment('simple_counter');

        $value = Redis::get('metrics:simple_counter');
        $this->assertSame('1', $value);
    }
}
