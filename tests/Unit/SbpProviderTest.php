<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Payments\Domain\Contracts\ProviderResponse;
use App\Payments\Domain\Exceptions\PaymentException;
use App\Payments\Domain\ValueObjects\ExternalId;
use App\Payments\Domain\ValueObjects\Money;
use App\Payments\Infrastructure\Observability\PaymentLogger;
use App\Payments\Infrastructure\Providers\SbpProvider;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class SbpProviderTest extends TestCase
{
    private SbpProvider $provider;

    private MockInterface $logger;

    private string $webhookSecret = 'test-webhook-secret';

    protected function setUp(): void
    {
        parent::setUp();

        $this->logger = Mockery::mock(PaymentLogger::class);
        $this->logger->shouldReceive('info')->andReturnNull()->byDefault();
        $this->logger->shouldReceive('warning')->andReturnNull()->byDefault();
        $this->logger->shouldReceive('error')->andReturnNull()->byDefault();

        $this->provider = new SbpProvider(
            merchantId:    'merchant-001',
            apiKey:        'api-key-test',
            webhookSecret: $this->webhookSecret,
            baseUrl:       'https://api.nspk.ru/sbp/v1/merchant-integrations',
            logger:        $this->logger,
        );
    }

    public function test_name_returns_sbp(): void
    {
        $this->assertSame('sbp', $this->provider->name());
    }

    public function test_create_payment_returns_provider_response(): void
    {
        Http::fake([
            '*/qrc/dynamic' => Http::response([
                'qrId'    => 'QR-001',
                'payload' => 'https://qr.nspk.ru/QR-001',
            ], 200),
        ]);

        $result = $this->provider->createPayment(
            paymentId:      'pay-123',
            amount:         Money::ofRub(50000),
            description:    'Test SBP payment',
            returnUrl:      'https://example.com/return',
            idempotencyKey: 'idem-key-1',
        );

        $this->assertInstanceOf(ProviderResponse::class, $result);
        $this->assertSame('QR-001', $result->externalId->toString());
        $this->assertSame('https://qr.nspk.ru/QR-001', $result->confirmationUrl);
        $this->assertSame('pending', $result->status);
    }

    public function test_create_payment_throws_when_api_returns_error(): void
    {
        Http::fake([
            '*/qrc/dynamic' => Http::response([], 500),
        ]);

        $this->expectException(PaymentException::class);
        $this->expectExceptionMessageMatches('/СБП: ошибка создания QR/');

        $this->provider->createPayment(
            paymentId:      'pay-123',
            amount:         Money::ofRub(50000),
            description:    'Test',
            returnUrl:      'https://example.com',
            idempotencyKey: 'idem-key-1',
        );
    }

    public function test_create_payment_throws_when_response_missing_qr_id(): void
    {
        Http::fake([
            '*/qrc/dynamic' => Http::response(['payload' => 'https://qr.nspk.ru/QR-001'], 200),
        ]);

        $this->expectException(PaymentException::class);
        $this->expectExceptionMessageMatches('/qrId/');

        $this->provider->createPayment(
            paymentId:      'pay-123',
            amount:         Money::ofRub(50000),
            description:    'Test',
            returnUrl:      'https://example.com',
            idempotencyKey: 'idem-key-1',
        );
    }

    public function test_create_payment_sends_bearer_token(): void
    {
        Http::fake([
            '*/qrc/dynamic' => Http::response(['qrId' => 'QR-001', 'payload' => 'https://qr.nspk.ru/QR-001'], 200),
        ]);

        $this->provider->createPayment(
            paymentId:      'pay-123',
            amount:         Money::ofRub(10000),
            description:    'Test',
            returnUrl:      'https://example.com',
            idempotencyKey: 'idem-key',
        );

        Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'Bearer api-key-test'));
    }

    public function test_get_payment_returns_succeeded_status(): void
    {
        Http::fake([
            '*/qrc/QR-001/status' => Http::response(['qrStatus' => 'PAID'], 200),
        ]);

        $result = $this->provider->getPayment(ExternalId::fromString('QR-001'));

        $this->assertSame('succeeded', $result->status);
        $this->assertSame('QR-001', $result->externalId->toString());
    }

    public function test_get_payment_returns_canceled_on_expired(): void
    {
        Http::fake([
            '*/qrc/*/status' => Http::response(['qrStatus' => 'EXPIRED'], 200),
        ]);

        $result = $this->provider->getPayment(ExternalId::fromString('QR-002'));

        $this->assertSame('canceled', $result->status);
    }

    public function test_get_payment_returns_pending_on_unknown_status(): void
    {
        Http::fake([
            '*/qrc/*/status' => Http::response(['qrStatus' => 'UNKNOWN_STATUS'], 200),
        ]);

        $result = $this->provider->getPayment(ExternalId::fromString('QR-003'));

        $this->assertSame('pending', $result->status);
    }

    public function test_get_payment_throws_when_api_fails(): void
    {
        Http::fake([
            '*/qrc/*/status' => Http::response([], 503),
        ]);

        $this->expectException(PaymentException::class);
        $this->expectExceptionMessageMatches('/СБП: ошибка запроса статуса/');

        $this->provider->getPayment(ExternalId::fromString('QR-001'));
    }

    public function test_refund_payment_returns_succeeded(): void
    {
        Http::fake([
            '*/refund' => Http::response(['refundStatus' => 'COMPLETED'], 200),
        ]);

        $result = $this->provider->refundPayment(
            externalId: ExternalId::fromString('QR-001'),
            amount:     Money::ofRub(50000),
        );

        $this->assertSame('succeeded', $result->status);
    }

    public function test_refund_payment_throws_when_declined(): void
    {
        Http::fake([
            '*/refund' => Http::response(['refundStatus' => 'DECLINED'], 200),
        ]);

        $this->expectException(PaymentException::class);
        $this->expectExceptionMessageMatches('/отклонён/');

        $this->provider->refundPayment(
            externalId: ExternalId::fromString('QR-001'),
            amount:     Money::ofRub(50000),
        );
    }

    public function test_refund_payment_throws_when_api_fails(): void
    {
        Http::fake([
            '*/refund' => Http::response([], 500),
        ]);

        $this->expectException(PaymentException::class);
        $this->expectExceptionMessageMatches('/СБП: ошибка возврата/');

        $this->provider->refundPayment(
            externalId: ExternalId::fromString('QR-001'),
            amount:     Money::ofRub(50000),
        );
    }

    public function test_verify_webhook_returns_true_with_valid_key_and_fields(): void
    {
        $result = $this->provider->verifyWebhook(
            ['qrId' => 'QR-001', 'status' => 'PAID'],
            ['x-api-key' => [$this->webhookSecret]],
        );

        $this->assertTrue($result);
    }

    public function test_verify_webhook_returns_false_with_invalid_key(): void
    {
        $this->logger->shouldReceive('warning')->once();

        $result = $this->provider->verifyWebhook(
            ['qrId' => 'QR-001', 'status' => 'PAID'],
            ['x-api-key' => ['wrong-secret']],
        );

        $this->assertFalse($result);
    }

    public function test_verify_webhook_returns_false_when_fields_missing(): void
    {
        $this->logger->shouldReceive('warning')->once();

        $result = $this->provider->verifyWebhook(
            ['qrId' => 'QR-001'], // missing status
            ['x-api-key' => [$this->webhookSecret]],
        );

        $this->assertFalse($result);
    }

    public function test_parse_webhook_maps_paid_to_succeeded(): void
    {
        $result = $this->provider->parseWebhook([
            'qrId'   => 'QR-001',
            'status' => 'PAID',
        ]);

        $this->assertSame('succeeded', $result->status);
        $this->assertSame('QR-001', $result->externalId->toString());
    }

    public function test_parse_webhook_maps_cancelled_to_canceled(): void
    {
        $result = $this->provider->parseWebhook([
            'qrId'   => 'QR-002',
            'status' => 'CANCELLED',
        ]);

        $this->assertSame('canceled', $result->status);
    }

    public function test_parse_webhook_maps_unknown_to_pending(): void
    {
        $result = $this->provider->parseWebhook([
            'qrId'   => 'QR-003',
            'status' => 'IN_PROGRESS',
        ]);

        $this->assertSame('pending', $result->status);
    }
}
