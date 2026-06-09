<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Payments\Infrastructure\CircuitBreaker\CircuitBreaker;
use App\Payments\Infrastructure\CircuitBreaker\CircuitState;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class CircuitBreakerTest extends TestCase
{
    private CircuitBreaker $cb;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cb = new CircuitBreaker(
            failureThreshold: 3,
            recoveryTimeoutSeconds: 10,
        );
        $this->cb->reset('test_provider');
    }

    protected function tearDown(): void
    {
        $this->cb->reset('test_provider');
        parent::tearDown();
    }

    public function test_starts_in_closed_state(): void
    {
        $this->assertSame(CircuitState::Closed, $this->cb->getState('test_provider'));
        $this->assertTrue($this->cb->isAvailable('test_provider'));
    }

    public function test_stays_closed_below_threshold(): void
    {
        $this->cb->recordFailure('test_provider');
        $this->cb->recordFailure('test_provider');

        $this->assertSame(CircuitState::Closed, $this->cb->getState('test_provider'));
        $this->assertTrue($this->cb->isAvailable('test_provider'));
    }

    public function test_opens_at_failure_threshold(): void
    {
        $this->cb->recordFailure('test_provider');
        $this->cb->recordFailure('test_provider');
        $this->cb->recordFailure('test_provider');

        $this->assertSame(CircuitState::Open, $this->cb->getState('test_provider'));
        $this->assertFalse($this->cb->isAvailable('test_provider'));
    }

    public function test_success_resets_failure_count(): void
    {
        $this->cb->recordFailure('test_provider');
        $this->cb->recordFailure('test_provider');
        $this->cb->recordSuccess('test_provider');

        // After reset, need 3 more failures to trip
        $this->cb->recordFailure('test_provider');
        $this->cb->recordFailure('test_provider');

        $this->assertSame(CircuitState::Closed, $this->cb->getState('test_provider'));
    }

    public function test_open_circuit_returns_retry_after(): void
    {
        $this->cb->recordFailure('test_provider');
        $this->cb->recordFailure('test_provider');
        $this->cb->recordFailure('test_provider');

        $retryAfter = $this->cb->retryAfterSeconds('test_provider');

        $this->assertGreaterThan(0, $retryAfter);
        $this->assertLessThanOrEqual(10, $retryAfter);
    }

    public function test_reset_returns_to_closed(): void
    {
        $this->cb->recordFailure('test_provider');
        $this->cb->recordFailure('test_provider');
        $this->cb->recordFailure('test_provider');

        $this->assertSame(CircuitState::Open, $this->cb->getState('test_provider'));

        $this->cb->reset('test_provider');

        $this->assertSame(CircuitState::Closed, $this->cb->getState('test_provider'));
        $this->assertTrue($this->cb->isAvailable('test_provider'));
    }

    public function test_half_open_transitions_to_closed_on_success(): void
    {
        // Trip the breaker
        $this->cb->recordFailure('test_provider');
        $this->cb->recordFailure('test_provider');
        $this->cb->recordFailure('test_provider');

        // Simulate recovery timeout elapsed by manipulating Redis
        Redis::set('cb:test_provider:opened_at', (string) (time() - 15));

        // Should transition to half-open and allow attempt
        $this->assertTrue($this->cb->isAvailable('test_provider'));
        $this->assertSame(CircuitState::HalfOpen, $this->cb->getState('test_provider'));

        // Success in half-open → closed
        $this->cb->recordSuccess('test_provider');
        $this->assertSame(CircuitState::Closed, $this->cb->getState('test_provider'));
    }

    public function test_half_open_trips_on_failure(): void
    {
        // Trip the breaker
        $this->cb->recordFailure('test_provider');
        $this->cb->recordFailure('test_provider');
        $this->cb->recordFailure('test_provider');

        // Simulate recovery timeout elapsed
        Redis::set('cb:test_provider:opened_at', (string) (time() - 15));

        // Transition to half-open
        $this->cb->isAvailable('test_provider');
        $this->assertSame(CircuitState::HalfOpen, $this->cb->getState('test_provider'));

        // Failure in half-open → open again
        $this->cb->recordFailure('test_provider');
        $this->assertSame(CircuitState::Open, $this->cb->getState('test_provider'));
    }

    public function test_independent_services(): void
    {
        $cb = new CircuitBreaker(failureThreshold: 2, recoveryTimeoutSeconds: 10);

        $cb->recordFailure('svc_a');
        $cb->recordFailure('svc_a');

        $this->assertSame(CircuitState::Open, $cb->getState('svc_a'));
        $this->assertSame(CircuitState::Closed, $cb->getState('svc_b'));

        $cb->reset('svc_a');
        $cb->reset('svc_b');
    }
}
