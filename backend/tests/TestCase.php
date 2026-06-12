<?php

namespace Tests;

use App\Contexts\Payments\Infrastructure\Observability\MetricsService;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Redis;
use Mockery;
use Tests\Feature\Observability\MetricsServiceTest;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Redirect payments log to null in all tests to avoid file permission issues
        config(['logging.channels.payments' => ['driver' => 'stack', 'channels' => ['null']]]);

        // Изоляция между тестами: антифрод-счётчики (velocity), идемпотентность и
        // прочее состояние живут в Redis. Без сброса счётчики копятся через весь
        // прогон и creation-heavy тесты упираются в лимит по IP → ложные падения.
        try {
            Redis::connection()->flushdb();
        } catch (\Throwable) {
            // Redis недоступен (часть unit-тестов его не требует) — пропускаем.
        }

        // Bind a no-op MetricsService so feature tests don't require a running Redis
        if (! $this instanceof MetricsServiceTest) {
            $mock = Mockery::mock(MetricsService::class);
            $mock->shouldReceive(
                'paymentCreated', 'paymentSucceeded', 'paymentCancelled',
                'paymentRefunded', 'paymentAmount', 'webhookProcessed', 'webhookFailed',
                'notificationSent', 'throttleRejected', 'disputeFiled', 'disputeResolved',
                'circuitBreakerStateChanged', 'increment', 'add', 'dump',
            )->andReturnNull()->byDefault();
            $this->app->instance(MetricsService::class, $mock);
        }
    }
}
