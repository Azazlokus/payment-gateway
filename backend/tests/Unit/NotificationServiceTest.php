<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contexts\Payments\Application\DTOs\PaymentResultDTO;
use App\Contexts\Payments\Infrastructure\Observability\MetricsService;
use App\Contexts\Payments\Infrastructure\Observability\NotificationService;
use App\Contexts\Payments\Infrastructure\Observability\PaymentLogger;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class NotificationServiceTest extends TestCase
{
    private NotificationService $service;

    private MockInterface $logger;

    private MockInterface $metrics;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logger = Mockery::mock(PaymentLogger::class);
        $this->logger->shouldReceive('info')->andReturnNull()->byDefault();
        $this->logger->shouldReceive('warning')->andReturnNull()->byDefault();

        $this->metrics = Mockery::mock(MetricsService::class);
        $this->metrics->shouldReceive('notificationSent')->andReturnNull()->byDefault();

        $this->service = new NotificationService($this->logger, $this->metrics);
    }

    private function makePayment(array $overrides = []): PaymentResultDTO
    {
        return new PaymentResultDTO(...array_merge([
            'paymentId' => 'pay-001',
            'status' => 'Succeeded',
            'amount' => 50000,
            'currency' => 'RUB',
            'externalId' => 'ext-001',
            'confirmationUrl' => null,
        ], $overrides));
    }

    public function test_notify_does_nothing_when_notification_url_absent(): void
    {
        Http::fake();

        $this->service->notify($this->makePayment(), []);

        Http::assertNothingSent();
    }

    public function test_notify_does_nothing_when_notification_url_empty_string(): void
    {
        Http::fake();

        $this->service->notify($this->makePayment(), ['notification_url' => '']);

        Http::assertNothingSent();
    }

    public function test_notify_sends_post_to_notification_url(): void
    {
        Http::fake(['https://example.com/notify' => Http::response([], 200)]);

        $this->service->notify(
            $this->makePayment(),
            ['notification_url' => 'https://example.com/notify'],
        );

        Http::assertSent(fn (Request $request) => $request->url() === 'https://example.com/notify' &&
            $request->method() === 'POST'
        );
    }

    public function test_notify_sends_correct_payload(): void
    {
        Http::fake(['https://example.com/notify' => Http::response([], 200)]);

        $payment = $this->makePayment([
            'paymentId' => 'pay-123',
            'status' => 'Succeeded',
            'amount' => 75000,
            'currency' => 'RUB',
            'externalId' => 'ext-456',
        ]);

        $this->service->notify($payment, ['notification_url' => 'https://example.com/notify']);

        Http::assertSent(fn (Request $request) => $request['event'] === 'payment.status_changed' &&
            $request['payment_id'] === 'pay-123' &&
            $request['status'] === 'Succeeded' &&
            $request['amount'] === 75000 &&
            $request['currency'] === 'RUB' &&
            $request['external_id'] === 'ext-456'
        );
    }

    public function test_notify_sends_x_signature_header(): void
    {
        Http::fake(['https://example.com/notify' => Http::response([], 200)]);

        $this->service->notify(
            $this->makePayment(),
            ['notification_url' => 'https://example.com/notify'],
        );

        Http::assertSent(fn (Request $request) => $request->hasHeader('X-Signature'));
    }

    public function test_notify_records_success_metric_on_200(): void
    {
        Http::fake(['https://example.com/notify' => Http::response([], 200)]);

        $this->metrics->shouldReceive('notificationSent')->once()->with(true);

        $this->service->notify(
            $this->makePayment(),
            ['notification_url' => 'https://example.com/notify'],
        );
    }

    public function test_notify_records_failure_metric_on_non_2xx(): void
    {
        Http::fake(['https://example.com/notify' => Http::response([], 500)]);

        $this->metrics->shouldReceive('notificationSent')->once()->with(false);

        $this->service->notify(
            $this->makePayment(),
            ['notification_url' => 'https://example.com/notify'],
        );
    }

    public function test_notify_records_failure_metric_on_network_error(): void
    {
        Http::fake(['https://example.com/notify' => fn () => throw new \Exception('Connection refused')]);

        $this->logger->shouldReceive('warning')->once()->with(
            'Outbound notification failed',
            Mockery::on(fn ($ctx) => str_contains($ctx['error'], 'Connection refused'))
        );

        $this->metrics->shouldReceive('notificationSent')->once()->with(false);

        $this->service->notify(
            $this->makePayment(),
            ['notification_url' => 'https://example.com/notify'],
        );
    }

    public function test_notify_does_not_throw_on_network_error(): void
    {
        Http::fake(['https://example.com/notify' => fn () => throw new \Exception('Timeout')]);

        // Не должно выбросить исключение
        $this->service->notify(
            $this->makePayment(),
            ['notification_url' => 'https://example.com/notify'],
        );

        $this->assertTrue(true);
    }
}
