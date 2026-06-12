<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contexts\Payments\Domain\Contracts\ProviderResponse;
use App\Contexts\Payments\Domain\Exceptions\PaymentException;
use App\Contexts\Payments\Domain\ValueObjects\ExternalId;
use App\Contexts\Payments\Domain\ValueObjects\Money;
use App\Contexts\Payments\Infrastructure\Observability\PaymentLogger;
use App\Contexts\Payments\Infrastructure\Providers\AlfaBankProvider;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class AlfaBankProviderTest extends TestCase
{
    private AlfaBankProvider $provider;

    private MockInterface $logger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logger = Mockery::mock(PaymentLogger::class);
        $this->logger->shouldReceive('info')->andReturnNull()->byDefault();
        $this->logger->shouldReceive('warning')->andReturnNull()->byDefault();
        $this->logger->shouldReceive('error')->andReturnNull()->byDefault();

        $this->provider = new AlfaBankProvider(
            login: 'test_login',
            password: 'test_password',
            baseUrl: 'https://pay.alfabank.ru/payment/rest',
            logger: $this->logger,
        );
    }

    public function test_name_returns_alfabank(): void
    {
        $this->assertSame('alfabank', $this->provider->name());
    }

    public function test_create_payment_returns_provider_response(): void
    {
        Http::fake([
            '*/register.do' => Http::response([
                'orderId' => 'alfa-order-uuid-001',
                'formUrl' => 'https://pay.alfabank.ru/payment/merchants/test/payment_ru.html?mdOrder=alfa-order-uuid-001',
            ], 200),
        ]);

        $result = $this->provider->createPayment(
            paymentId: 'internal-pay-123',
            amount: Money::ofRub(75000),
            description: 'Test Alfa-Bank payment',
            returnUrl: 'https://example.com/return',
            idempotencyKey: 'idem-key-1',
        );

        $this->assertInstanceOf(ProviderResponse::class, $result);
        $this->assertSame('alfa-order-uuid-001', $result->externalId->toString());
        $this->assertStringContainsString('alfa-order-uuid-001', $result->confirmationUrl);
        $this->assertSame('pending', $result->status);
    }

    public function test_create_payment_throws_on_http_error(): void
    {
        Http::fake([
            '*/register.do' => Http::response([], 500),
        ]);

        $this->expectException(PaymentException::class);
        $this->expectExceptionMessageMatches('/Альфа-Банк: HTTP ошибка/');

        $this->provider->createPayment(
            paymentId: 'internal-pay-123',
            amount: Money::ofRub(75000),
            description: 'Test',
            returnUrl: 'https://example.com',
            idempotencyKey: 'idem-key-1',
        );
    }

    public function test_create_payment_throws_on_api_error_code(): void
    {
        Http::fake([
            '*/register.do' => Http::response([
                'errorCode' => '7',
                'errorMessage' => 'Merchant not found',
            ], 200),
        ]);

        $this->expectException(PaymentException::class);
        $this->expectExceptionMessageMatches('/Merchant not found/');

        $this->provider->createPayment(
            paymentId: 'internal-pay-123',
            amount: Money::ofRub(75000),
            description: 'Test',
            returnUrl: 'https://example.com',
            idempotencyKey: 'idem-key-1',
        );
    }

    public function test_create_payment_throws_when_order_id_missing(): void
    {
        Http::fake([
            '*/register.do' => Http::response(['formUrl' => 'https://example.com'], 200),
        ]);

        $this->expectException(PaymentException::class);
        $this->expectExceptionMessageMatches('/orderId/');

        $this->provider->createPayment(
            paymentId: 'internal-pay-123',
            amount: Money::ofRub(75000),
            description: 'Test',
            returnUrl: 'https://example.com',
            idempotencyKey: 'idem-key-1',
        );
    }

    public function test_create_payment_sends_form_fields(): void
    {
        Http::fake([
            '*/register.do' => Http::response(['orderId' => 'order-001', 'formUrl' => ''], 200),
        ]);

        $this->provider->createPayment(
            paymentId: 'internal-pay-123',
            amount: Money::ofRub(75000),
            description: 'Test payment',
            returnUrl: 'https://example.com/return',
            idempotencyKey: 'idem-key',
        );

        Http::assertSent(fn (Request $request) => $request->url() === 'https://pay.alfabank.ru/payment/rest/register.do' &&
            $request->isForm() &&
            $request['orderNumber'] === 'internal-pay-123' &&
            $request['amount'] === 75000 &&
            $request['userName'] === 'test_login'
        );
    }

    public function test_get_payment_returns_succeeded_on_status_2(): void
    {
        Http::fake([
            '*/getOrderStatusExtended.do' => Http::response(['orderStatus' => 2], 200),
        ]);

        $result = $this->provider->getPayment(ExternalId::fromString('alfa-order-001'));

        $this->assertSame('succeeded', $result->status);
    }

    public function test_get_payment_returns_pending_on_unknown_status(): void
    {
        Http::fake([
            '*/getOrderStatusExtended.do' => Http::response(['orderStatus' => 0], 200),
        ]);

        $result = $this->provider->getPayment(ExternalId::fromString('alfa-order-001'));

        $this->assertSame('pending', $result->status);
    }

    public function test_get_payment_returns_refunded_on_status_6(): void
    {
        Http::fake([
            '*/getOrderStatusExtended.do' => Http::response(['orderStatus' => 6], 200),
        ]);

        $result = $this->provider->getPayment(ExternalId::fromString('alfa-order-001'));

        $this->assertSame('refunded', $result->status);
    }

    public function test_get_payment_throws_when_api_fails(): void
    {
        Http::fake([
            '*/getOrderStatusExtended.do' => Http::response([], 503),
        ]);

        $this->expectException(PaymentException::class);
        $this->expectExceptionMessageMatches('/Альфа-Банк: ошибка запроса статуса/');

        $this->provider->getPayment(ExternalId::fromString('alfa-order-001'));
    }

    public function test_refund_payment_returns_succeeded(): void
    {
        Http::fake([
            '*/refund.do' => Http::response(['errorCode' => '0'], 200),
        ]);

        $result = $this->provider->refundPayment(
            externalId: ExternalId::fromString('alfa-order-001'),
            amount: Money::ofRub(30000),
        );

        $this->assertSame('succeeded', $result->status);
    }

    public function test_refund_payment_throws_on_api_error(): void
    {
        Http::fake([
            '*/refund.do' => Http::response([
                'errorCode' => '7',
                'errorMessage' => 'Refund not allowed',
            ], 200),
        ]);

        $this->expectException(PaymentException::class);
        $this->expectExceptionMessageMatches('/Refund not allowed/');

        $this->provider->refundPayment(
            externalId: ExternalId::fromString('alfa-order-001'),
            amount: Money::ofRub(30000),
        );
    }

    public function test_refund_payment_throws_on_http_error(): void
    {
        Http::fake([
            '*/refund.do' => Http::response([], 500),
        ]);

        $this->expectException(PaymentException::class);
        $this->expectExceptionMessageMatches('/HTTP ошибка возврата/');

        $this->provider->refundPayment(
            externalId: ExternalId::fromString('alfa-order-001'),
            amount: Money::ofRub(30000),
        );
    }

    public function test_verify_webhook_returns_true_with_required_fields(): void
    {
        $result = $this->provider->verifyWebhook(
            ['mdOrder' => 'alfa-order-001', 'operation' => 'deposited'],
            [],
        );

        $this->assertTrue($result);
    }

    public function test_verify_webhook_returns_false_when_md_order_missing(): void
    {
        $result = $this->provider->verifyWebhook(
            ['operation' => 'deposited'],
            [],
        );

        $this->assertFalse($result);
    }

    public function test_verify_webhook_returns_false_when_operation_missing(): void
    {
        $result = $this->provider->verifyWebhook(
            ['mdOrder' => 'alfa-order-001'],
            [],
        );

        $this->assertFalse($result);
    }

    public function test_parse_webhook_maps_deposited_to_succeeded(): void
    {
        $result = $this->provider->parseWebhook([
            'mdOrder' => 'alfa-order-001',
            'operation' => 'deposited',
        ]);

        $this->assertSame('succeeded', $result->status);
        $this->assertSame('alfa-order-001', $result->externalId->toString());
    }

    public function test_parse_webhook_maps_refunded_to_refunded(): void
    {
        $result = $this->provider->parseWebhook([
            'mdOrder' => 'alfa-order-001',
            'operation' => 'refunded',
        ]);

        $this->assertSame('refunded', $result->status);
    }

    public function test_parse_webhook_maps_reversed_to_canceled(): void
    {
        $result = $this->provider->parseWebhook([
            'mdOrder' => 'alfa-order-001',
            'operation' => 'reversed',
        ]);

        $this->assertSame('canceled', $result->status);
    }

    public function test_parse_webhook_maps_declined_by_timeout_to_canceled(): void
    {
        $result = $this->provider->parseWebhook([
            'mdOrder' => 'alfa-order-001',
            'operation' => 'declinedByTimeout',
        ]);

        $this->assertSame('canceled', $result->status);
    }

    public function test_parse_webhook_maps_unknown_operation_to_pending(): void
    {
        $result = $this->provider->parseWebhook([
            'mdOrder' => 'alfa-order-001',
            'operation' => 'somethingElse',
        ]);

        $this->assertSame('pending', $result->status);
    }
}
