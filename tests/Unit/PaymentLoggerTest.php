<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Payments\Infrastructure\Observability\PaymentLogger;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class PaymentLoggerTest extends TestCase
{
    private PaymentLogger $logger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->logger = new PaymentLogger;
    }

    public function test_info_logs_to_payments_channel(): void
    {
        Log::shouldReceive('channel')
            ->once()
            ->with('payments')
            ->andReturnSelf();

        Log::shouldReceive('info')
            ->once()
            ->with('Test message', \Mockery::on(fn ($ctx) => $ctx['service'] === 'payment-gateway'));

        $this->logger->info('Test message');
    }

    public function test_error_logs_to_payments_channel(): void
    {
        Log::shouldReceive('channel')
            ->once()
            ->with('payments')
            ->andReturnSelf();

        Log::shouldReceive('error')
            ->once()
            ->with('Error message', \Mockery::any());

        $this->logger->error('Error message');
    }

    public function test_warning_logs_to_payments_channel(): void
    {
        Log::shouldReceive('channel')
            ->once()
            ->with('payments')
            ->andReturnSelf();

        Log::shouldReceive('warning')
            ->once()
            ->with('Warning message', \Mockery::any());

        $this->logger->warning('Warning message');
    }

    public function test_enriched_context_contains_service_key(): void
    {
        Log::shouldReceive('channel')->andReturnSelf();
        Log::shouldReceive('info')
            ->once()
            ->with(\Mockery::any(), \Mockery::on(fn ($ctx) =>
                $ctx['service'] === 'payment-gateway'
            ));

        $this->logger->info('msg');
    }

    public function test_enriched_context_contains_environment(): void
    {
        Log::shouldReceive('channel')->andReturnSelf();
        Log::shouldReceive('info')
            ->once()
            ->with(\Mockery::any(), \Mockery::on(fn ($ctx) =>
                isset($ctx['environment'])
            ));

        $this->logger->info('msg');
    }

    public function test_enriched_context_contains_timestamp(): void
    {
        Log::shouldReceive('channel')->andReturnSelf();
        Log::shouldReceive('info')
            ->once()
            ->with(\Mockery::any(), \Mockery::on(fn ($ctx) =>
                isset($ctx['timestamp']) && str_contains($ctx['timestamp'], 'T')
            ));

        $this->logger->info('msg');
    }

    public function test_caller_context_is_merged_into_enriched(): void
    {
        Log::shouldReceive('channel')->andReturnSelf();
        Log::shouldReceive('info')
            ->once()
            ->with('msg', \Mockery::on(fn ($ctx) =>
                $ctx['payment_id'] === 'pay-123' &&
                $ctx['service'] === 'payment-gateway'
            ));

        $this->logger->info('msg', ['payment_id' => 'pay-123']);
    }

    public function test_caller_context_overrides_enriched_keys(): void
    {
        Log::shouldReceive('channel')->andReturnSelf();
        Log::shouldReceive('info')
            ->once()
            ->with('msg', \Mockery::on(fn ($ctx) =>
                $ctx['service'] === 'custom-service'
            ));

        $this->logger->info('msg', ['service' => 'custom-service']);
    }

    public function test_correlation_id_included_from_request_header(): void
    {
        $this->withHeaders(['X-Correlation-Id' => 'trace-abc-123']);

        Log::shouldReceive('channel')->andReturnSelf();
        Log::shouldReceive('info')
            ->once()
            ->with('msg', \Mockery::on(fn ($ctx) =>
                $ctx['correlation_id'] === 'trace-abc-123'
            ));

        $this->logger->info('msg');
    }
}
