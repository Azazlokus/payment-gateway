<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Payments\Domain\Contracts\ProviderResponse;
use App\Payments\Domain\Exceptions\PaymentException;
use App\Payments\Domain\ValueObjects\ExternalId;
use App\Payments\Domain\ValueObjects\Money;
use App\Payments\Infrastructure\Observability\PaymentLogger;
use App\Payments\Infrastructure\Providers\CloudPaymentsProvider;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class CloudPaymentsProviderTest extends TestCase
{
    private CloudPaymentsProvider $provider;

    private MockInterface $logger;

    private string $publicId  = 'pk_test_public';
    private string $apiSecret = 'test_api_secret';

    protected function setUp(): void
    {
        parent::setUp();

        $this->logger = Mockery::mock(PaymentLogger::class);
        $this->logger->shouldReceive('info')->andReturnNull()->byDefault();
        $this->logger->shouldReceive('warning')->andReturnNull()->byDefault();
        $this->logger->shouldReceive('error')->andReturnNull()->byDefault();

        $this->provider = new CloudPaymentsProvider(
            publicId:  $this->publicId,
            apiSecret: $this->apiSecret,
            logger:    $this->logger,
        );
    }

    public function test_name_returns_cloudpayments(): void
    {
        $this->assertSame('cloudpayments', $this->provider->name());
    }

    public function test_create_payment_returns_provider_response(): void
    {
        Http::fake([
            '*/payments/link/create' => Http::response([
                'Success' => true,
                'Model'   => [
                    'Url'           => 'https://pay.cloudpayments.ru/order/pay/cp-link-001',
                    'TransactionId' => 12345678,
                ],
            ], 200),
        ]);

        $result = $this->provider->createPayment(
            paymentId:      'internal-pay-001',
            amount:         Money::ofRub(50000),
            description:    'Test CloudPayments payment',
            returnUrl:      'https://example.com/return',
            idempotencyKey: 'idem-key-1',
        );

        $this->assertInstanceOf(ProviderResponse::class, $result);
        $this->assertSame('12345678', $result->externalId->toString());
        $this->assertSame('https://pay.cloudpayments.ru/order/pay/cp-link-001', $result->confirmationUrl);
        $this->assertSame('pending', $result->status);
    }

    public function test_create_payment_throws_on_http_error(): void
    {
        Http::fake([
            '*/payments/link/create' => Http::response([], 500),
        ]);

        $this->expectException(PaymentException::class);
        $this->expectExceptionMessageMatches('/CloudPayments: HTTP ошибка/');

        $this->provider->createPayment(
            paymentId:      'pay-001',
            amount:         Money::ofRub(50000),
            description:    'Test',
            returnUrl:      'https://example.com',
            idempotencyKey: 'idem-key',
        );
    }

    public function test_create_payment_throws_when_success_is_false(): void
    {
        Http::fake([
            '*/payments/link/create' => Http::response([
                'Success' => false,
                'Message' => 'Invalid public id',
            ], 200),
        ]);

        $this->expectException(PaymentException::class);
        $this->expectExceptionMessageMatches('/Invalid public id/');

        $this->provider->createPayment(
            paymentId:      'pay-001',
            amount:         Money::ofRub(50000),
            description:    'Test',
            returnUrl:      'https://example.com',
            idempotencyKey: 'idem-key',
        );
    }

    public function test_create_payment_throws_when_url_missing(): void
    {
        Http::fake([
            '*/payments/link/create' => Http::response([
                'Success' => true,
                'Model'   => ['TransactionId' => 12345],
            ], 200),
        ]);

        $this->expectException(PaymentException::class);
        $this->expectExceptionMessageMatches('/Url/');

        $this->provider->createPayment(
            paymentId:      'pay-001',
            amount:         Money::ofRub(50000),
            description:    'Test',
            returnUrl:      'https://example.com',
            idempotencyKey: 'idem-key',
        );
    }

    public function test_create_payment_uses_basic_auth(): void
    {
        Http::fake([
            '*/payments/link/create' => Http::response([
                'Success' => true,
                'Model'   => ['Url' => 'https://pay.cloudpayments.ru/link', 'TransactionId' => 1],
            ], 200),
        ]);

        $this->provider->createPayment(
            paymentId:      'pay-001',
            amount:         Money::ofRub(10000),
            description:    'Test',
            returnUrl:      'https://example.com',
            idempotencyKey: 'idem-key',
        );

        Http::assertSent(fn (Request $request) =>
            $request->hasHeader('Authorization') &&
            str_starts_with($request->header('Authorization')[0], 'Basic ')
        );
    }

    public function test_create_payment_converts_kopecks_to_rubles(): void
    {
        Http::fake([
            '*/payments/link/create' => Http::response([
                'Success' => true,
                'Model'   => ['Url' => 'https://pay.cloudpayments.ru/link', 'TransactionId' => 1],
            ], 200),
        ]);

        $this->provider->createPayment(
            paymentId:      'pay-001',
            amount:         Money::ofRub(99999), // 999.99 RUB
            description:    'Test',
            returnUrl:      'https://example.com',
            idempotencyKey: 'idem-key',
        );

        Http::assertSent(fn (Request $request) => $request['Amount'] === 999.99);
    }

    public function test_get_payment_returns_succeeded(): void
    {
        Http::fake([
            '*/payments/find' => Http::response([
                'Success' => true,
                'Model'   => [
                    'TransactionId' => 12345678,
                    'Status'        => 'Completed',
                ],
            ], 200),
        ]);

        $result = $this->provider->getPayment(ExternalId::fromString('internal-pay-001'));

        $this->assertSame('succeeded', $result->status);
        $this->assertSame('12345678', $result->externalId->toString());
    }

    public function test_get_payment_returns_canceled_on_declined(): void
    {
        Http::fake([
            '*/payments/find' => Http::response([
                'Success' => true,
                'Model'   => ['TransactionId' => 1, 'Status' => 'Declined'],
            ], 200),
        ]);

        $result = $this->provider->getPayment(ExternalId::fromString('pay-001'));

        $this->assertSame('canceled', $result->status);
    }

    public function test_get_payment_throws_on_http_error(): void
    {
        Http::fake([
            '*/payments/find' => Http::response([], 500),
        ]);

        $this->expectException(PaymentException::class);
        $this->expectExceptionMessageMatches('/CloudPayments: ошибка запроса статуса/');

        $this->provider->getPayment(ExternalId::fromString('pay-001'));
    }

    public function test_refund_payment_returns_succeeded(): void
    {
        Http::fake([
            '*/payments/refund' => Http::response(['Success' => true], 200),
        ]);

        $result = $this->provider->refundPayment(
            externalId: ExternalId::fromString('12345678'),
            amount:     Money::ofRub(50000),
        );

        $this->assertSame('succeeded', $result->status);
    }

    public function test_refund_payment_throws_when_success_false(): void
    {
        Http::fake([
            '*/payments/refund' => Http::response([
                'Success' => false,
                'Message' => 'Refund amount exceeds transaction amount',
            ], 200),
        ]);

        $this->expectException(PaymentException::class);
        $this->expectExceptionMessageMatches('/Refund amount exceeds/');

        $this->provider->refundPayment(
            externalId: ExternalId::fromString('12345678'),
            amount:     Money::ofRub(50000),
        );
    }

    public function test_refund_payment_throws_on_http_error(): void
    {
        Http::fake([
            '*/payments/refund' => Http::response([], 500),
        ]);

        $this->expectException(PaymentException::class);
        $this->expectExceptionMessageMatches('/CloudPayments: HTTP ошибка возврата/');

        $this->provider->refundPayment(
            externalId: ExternalId::fromString('12345678'),
            amount:     Money::ofRub(50000),
        );
    }

    public function test_verify_webhook_returns_true_with_valid_hmac(): void
    {
        $payload = ['TransactionId' => 12345, 'Status' => 'Completed'];
        $body    = json_encode($payload);
        $hmac    = base64_encode(hash_hmac('sha256', (string) $body, $this->apiSecret, true));

        // Подменяем request() так чтобы getContent() возвращал нужное тело
        $request = \Illuminate\Http\Request::create('/webhook/cloudpayments', 'POST', [], [], [], [], (string) $body);
        $request->headers->set('Content-HMAC', $hmac);
        $this->app->instance('request', $request);

        $result = $this->provider->verifyWebhook(
            $payload,
            ['content-hmac' => [$hmac]],
        );

        $this->assertTrue($result);
    }

    public function test_verify_webhook_returns_false_with_invalid_hmac(): void
    {
        $this->logger->shouldReceive('warning')->once();

        $result = $this->provider->verifyWebhook(
            ['TransactionId' => 12345, 'Status' => 'Completed'],
            ['content-hmac' => ['invalid-hmac']],
        );

        $this->assertFalse($result);
    }

    public function test_parse_webhook_maps_completed_to_succeeded(): void
    {
        $result = $this->provider->parseWebhook([
            'TransactionId' => '12345678',
            'Status'        => 'Completed',
        ]);

        $this->assertSame('succeeded', $result->status);
        $this->assertSame('12345678', $result->externalId->toString());
    }

    public function test_parse_webhook_maps_refunded_with_amount(): void
    {
        $result = $this->provider->parseWebhook([
            'TransactionId' => '12345678',
            'Status'        => 'Refunded',
            'Amount'        => 500.00,
        ]);

        $this->assertSame('refunded', $result->status);
        $this->assertSame(50000, $result->refundAmountKopecks);
    }

    public function test_parse_webhook_maps_cancelled_to_canceled(): void
    {
        $result = $this->provider->parseWebhook([
            'TransactionId' => '12345678',
            'Status'        => 'Cancelled',
        ]);

        $this->assertSame('canceled', $result->status);
        $this->assertNull($result->refundAmountKopecks);
    }

    public function test_parse_webhook_maps_unknown_status_to_pending(): void
    {
        $result = $this->provider->parseWebhook([
            'TransactionId' => '12345678',
            'Status'        => 'Created',
        ]);

        $this->assertSame('pending', $result->status);
    }
}
