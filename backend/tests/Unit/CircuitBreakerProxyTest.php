<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contexts\Payments\Domain\Contracts\PaymentProviderInterface;
use App\Contexts\Payments\Domain\Contracts\ProviderResponse;
use App\Contexts\Payments\Domain\Exceptions\PaymentException;
use App\Contexts\Payments\Domain\ValueObjects\ExternalId;
use App\Contexts\Payments\Domain\ValueObjects\Money;
use App\Contexts\Payments\Infrastructure\CircuitBreaker\CircuitBreakerInterface;
use App\Contexts\Payments\Infrastructure\CircuitBreaker\CircuitBreakerProviderProxy;
use App\Contexts\Payments\Infrastructure\CircuitBreaker\CircuitOpenException;
use App\Contexts\Payments\Infrastructure\CircuitBreaker\CircuitState;
use App\Contexts\Payments\Infrastructure\Observability\MetricsService;
use App\Contexts\Payments\Infrastructure\Observability\PaymentLogger;
use Mockery;
use PHPUnit\Framework\TestCase;

class CircuitBreakerProxyTest extends TestCase
{
    private CircuitBreakerInterface $cb;

    private PaymentProviderInterface $innerProvider;

    private CircuitBreakerProviderProxy $proxy;

    private PaymentLogger $logger;

    private MetricsService $metrics;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cb = Mockery::mock(CircuitBreakerInterface::class);
        $this->innerProvider = Mockery::mock(PaymentProviderInterface::class);
        $this->logger = Mockery::mock(PaymentLogger::class)->shouldIgnoreMissing();
        $this->metrics = Mockery::mock(MetricsService::class)->shouldIgnoreMissing();

        $this->innerProvider->shouldReceive('name')->andReturn('test_provider');

        $this->proxy = new CircuitBreakerProviderProxy(
            inner: $this->innerProvider,
            circuitBreaker: $this->cb,
            logger: $this->logger,
            metrics: $this->metrics,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_delegates_name_to_inner(): void
    {
        $this->assertSame('test_provider', $this->proxy->name());
    }

    public function test_successful_call_records_success(): void
    {
        $response = new ProviderResponse(
            externalId: ExternalId::fromString('ext-123'),
            confirmationUrl: 'https://pay.example.com',
            status: 'pending',
        );

        $this->cb->shouldReceive('isAvailable')->with('test_provider')->andReturn(true);
        $this->cb->shouldReceive('recordSuccess')->with('test_provider')->once();

        $this->innerProvider->shouldReceive('createPayment')
            ->once()
            ->andReturn($response);

        $result = $this->proxy->createPayment('p1', Money::ofRub(10_000), 'test', 'https://ret.url', 'idem-1');

        $this->assertSame('pending', $result->status);
    }

    public function test_failed_call_records_failure(): void
    {
        $this->cb->shouldReceive('isAvailable')->with('test_provider')->andReturn(true);
        $this->cb->shouldReceive('recordFailure')->with('test_provider')->once();
        $this->cb->shouldReceive('getState')->andReturn(CircuitState::Closed);

        $this->innerProvider->shouldReceive('createPayment')
            ->once()
            ->andThrow(new PaymentException('Connection timeout'));

        $this->expectException(PaymentException::class);
        $this->expectExceptionMessage('Connection timeout');

        $this->proxy->createPayment('p1', Money::ofRub(10_000), 'test', 'https://ret.url', 'idem-1');
    }

    public function test_throws_circuit_open_when_unavailable(): void
    {
        $this->cb->shouldReceive('isAvailable')->with('test_provider')->andReturn(false);
        $this->cb->shouldReceive('retryAfterSeconds')->with('test_provider')->andReturn(15);

        $this->innerProvider->shouldNotReceive('createPayment');

        $this->expectException(CircuitOpenException::class);

        $this->proxy->createPayment('p1', Money::ofRub(10_000), 'test', 'https://ret.url', 'idem-1');
    }

    public function test_circuit_open_exception_has_retry_after(): void
    {
        $this->cb->shouldReceive('isAvailable')->with('test_provider')->andReturn(false);
        $this->cb->shouldReceive('retryAfterSeconds')->with('test_provider')->andReturn(25);

        try {
            $this->proxy->createPayment('p1', Money::ofRub(10_000), 'test', 'https://ret.url', 'idem-1');
            $this->fail('Expected CircuitOpenException');
        } catch (CircuitOpenException $e) {
            $this->assertSame('test_provider', $e->provider);
            $this->assertSame(25, $e->retryAfterSeconds);
        }
    }

    public function test_verify_webhook_bypasses_circuit_breaker(): void
    {
        // Circuit breaker should NOT be checked for webhook verification
        $this->cb->shouldNotReceive('isAvailable');

        $this->innerProvider->shouldReceive('verifyWebhook')
            ->with(['event' => 'test'], [])
            ->andReturn(true);

        $result = $this->proxy->verifyWebhook(['event' => 'test'], []);

        $this->assertTrue($result);
    }

    public function test_refund_goes_through_circuit_breaker(): void
    {
        $response = new ProviderResponse(
            externalId: ExternalId::fromString('ext-123'),
            confirmationUrl: '',
            status: 'succeeded',
        );

        $this->cb->shouldReceive('isAvailable')->with('test_provider')->andReturn(true);
        $this->cb->shouldReceive('recordSuccess')->with('test_provider')->once();

        $this->innerProvider->shouldReceive('refundPayment')
            ->once()
            ->andReturn($response);

        $result = $this->proxy->refundPayment(ExternalId::fromString('ext-123'), Money::ofRub(5_000));

        $this->assertSame('succeeded', $result->status);
    }
}
