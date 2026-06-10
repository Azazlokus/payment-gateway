<?php

namespace Tests;

use App\Payments\Infrastructure\Observability\MetricsService;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Mockery;
use Tests\Feature\Observability\MetricsServiceTest;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Redirect payments log to null in all tests to avoid file permission issues
        config(['logging.channels.payments' => ['driver' => 'stack', 'channels' => ['null']]]);

        // Bind a no-op MetricsService so feature tests don't require a running Redis
        if (! $this instanceof MetricsServiceTest) {
            $mock = Mockery::mock(MetricsService::class);
            $mock->shouldReceive('paymentCreated', 'paymentSucceeded', 'paymentCancelled',
                'paymentRefunded', 'paymentAmount', 'webhookProcessed', 'webhookFailed',
                'notificationSent', 'increment', 'add', 'dump')
                ->andReturnNull()
                ->byDefault();
            $this->app->instance(MetricsService::class, $mock);
        }
    }
}
