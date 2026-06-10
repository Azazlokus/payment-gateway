<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Payments\Infrastructure\Antifraud\VelocityChecker;
use App\Payments\Infrastructure\Antifraud\VelocityLimitExceededException;
use App\Payments\Infrastructure\Antifraud\VelocityRule;
use App\Payments\Infrastructure\Observability\MetricsService;
use App\Payments\Infrastructure\Observability\PaymentLogger;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class VelocityCheckerTest extends TestCase
{
    private VelocityChecker $checker;

    protected function setUp(): void
    {
        parent::setUp();

        $logger = \Mockery::mock(PaymentLogger::class)->shouldIgnoreMissing();
        $metrics = \Mockery::mock(MetricsService::class)->shouldIgnoreMissing();

        $this->checker = new VelocityChecker($logger, $metrics);
    }

    protected function tearDown(): void
    {
        // Clean up Redis keys
        $keys = Redis::keys('velocity:*');
        if (! empty($keys)) {
            Redis::del(...$keys);
        }
        \Mockery::close();
        parent::tearDown();
    }

    public function test_allows_request_below_count_limit(): void
    {
        $this->checker->addRule(new VelocityRule('ip', maxCount: 3, windowSeconds: 60));

        $dims = ['ip' => '10.0.0.1'];

        $this->checker->record($dims, 1000, 'evt-1');
        $this->checker->record($dims, 1000, 'evt-2');

        // 2 events recorded, limit is 3 — should pass
        $this->checker->check($dims, 1000);
        $this->assertTrue(true); // no exception
    }

    public function test_blocks_at_count_limit(): void
    {
        $this->checker->addRule(new VelocityRule('ip', maxCount: 2, windowSeconds: 60));

        $dims = ['ip' => '10.0.0.2'];

        $this->checker->record($dims, 1000, 'evt-1');
        $this->checker->record($dims, 1000, 'evt-2');

        $this->expectException(VelocityLimitExceededException::class);
        $this->checker->check($dims, 1000);
    }

    public function test_allows_request_below_amount_limit(): void
    {
        $this->checker->addRule(new VelocityRule('user_id', maxCount: 100, windowSeconds: 60, maxAmountKopecks: 50_000));

        $dims = ['user_id' => '42'];

        $this->checker->record($dims, 20_000, 'evt-1');

        // 20000 recorded + 20000 pending = 40000, under 50000 limit
        $this->checker->check($dims, 20_000);
        $this->assertTrue(true);
    }

    public function test_blocks_at_amount_limit(): void
    {
        $this->checker->addRule(new VelocityRule('user_id', maxCount: 100, windowSeconds: 60, maxAmountKopecks: 50_000));

        $dims = ['user_id' => '43'];

        $this->checker->record($dims, 30_000, 'evt-1');

        // 30000 recorded + 25000 pending = 55000, over 50000 limit
        $this->expectException(VelocityLimitExceededException::class);
        $this->checker->check($dims, 25_000);
    }

    public function test_skips_null_dimensions(): void
    {
        $this->checker->addRule(new VelocityRule('user_id', maxCount: 1, windowSeconds: 60));

        // user_id is null — rule should be skipped
        $dims = ['ip' => '10.0.0.3', 'user_id' => null];

        $this->checker->check($dims, 1000);
        $this->assertTrue(true);
    }

    public function test_independent_dimensions(): void
    {
        $this->checker->addRule(new VelocityRule('ip', maxCount: 2, windowSeconds: 60));

        $this->checker->record(['ip' => '10.0.0.4'], 1000, 'evt-1');
        $this->checker->record(['ip' => '10.0.0.4'], 1000, 'evt-2');

        // Different IP should not be affected
        $this->checker->check(['ip' => '10.0.0.5'], 1000);
        $this->assertTrue(true);
    }

    public function test_exception_contains_dimension_info(): void
    {
        $this->checker->addRule(new VelocityRule('ip', maxCount: 1, windowSeconds: 60));

        $dims = ['ip' => '10.0.0.6'];
        $this->checker->record($dims, 1000, 'evt-1');

        try {
            $this->checker->check($dims, 1000);
            $this->fail('Expected VelocityLimitExceededException');
        } catch (VelocityLimitExceededException $e) {
            $this->assertSame('ip', $e->dimension);
            $this->assertSame('10.0.0.6', $e->key);
            $this->assertStringContainsString('max 1 per 60s', $e->rule);
        }
    }

    public function test_multiple_rules_all_checked(): void
    {
        $this->checker->addRule(new VelocityRule('ip', maxCount: 10, windowSeconds: 60));
        $this->checker->addRule(new VelocityRule('user_id', maxCount: 1, windowSeconds: 60));

        $dims = ['ip' => '10.0.0.7', 'user_id' => '99'];

        $this->checker->record($dims, 1000, 'evt-1');

        // IP limit (10) not reached, but user_id limit (1) reached
        $this->expectException(VelocityLimitExceededException::class);
        $this->checker->check($dims, 1000);
    }
}
