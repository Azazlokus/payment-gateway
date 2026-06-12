<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contexts\Payments\Domain\Contracts\ProviderResponse;
use App\Contexts\Payments\Domain\Exceptions\PaymentException;
use App\Contexts\Payments\Domain\ValueObjects\ExternalId;
use App\Contexts\Payments\Domain\ValueObjects\Money;
use App\Contexts\Payments\Infrastructure\Observability\PaymentLogger;
use App\Contexts\Payments\Infrastructure\Providers\YooKassaProvider;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;
use YooKassa\Client;
use YooKassa\Model\Payment\Confirmation\ConfirmationRedirect;
use YooKassa\Model\Payment\Payment;
use YooKassa\Request\Payments\CreatePaymentResponse;
use YooKassa\Request\Refunds\CreateRefundResponse;

class YooKassaProviderTest extends TestCase
{
    private MockInterface $yooKassaClient;

    private YooKassaProvider $provider;

    private MockInterface $logger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->yooKassaClient = Mockery::mock(Client::class);
        $this->yooKassaClient->shouldReceive('setAuth')->andReturnSelf()->byDefault();

        $this->logger = Mockery::mock(PaymentLogger::class);
        $this->logger->shouldReceive('info')->andReturnNull()->byDefault();
        $this->logger->shouldReceive('error')->andReturnNull()->byDefault();
        $this->logger->shouldReceive('warning')->andReturnNull()->byDefault();

        $this->provider = new YooKassaProvider(
            shopId: '100500',
            secretKey: 'test_secret',
            logger: $this->logger,
            client: $this->yooKassaClient,
        );
    }

    public function test_name_returns_yookassa(): void
    {
        $this->assertSame('yookassa', $this->provider->name());
    }

    public function test_create_payment_returns_provider_response(): void
    {
        $confirmation = Mockery::mock(ConfirmationRedirect::class);
        $confirmation->shouldReceive('getConfirmationUrl')
            ->once()
            ->andReturn('https://yookassa.ru/checkout/payments/abc123');

        $response = Mockery::mock(CreatePaymentResponse::class);
        $response->shouldReceive('getId')->andReturn('22d65900-000f-5000-a000-10d000000000');
        $response->shouldReceive('getStatus')->andReturn('pending');
        $response->shouldReceive('getConfirmation')->andReturn($confirmation);
        $response->shouldReceive('getPaymentMethod')->andReturnNull()->byDefault();
        $response->shouldReceive('jsonSerialize')->andReturn(['id' => '22d65900-000f-5000-a000-10d000000000', 'status' => 'pending']);

        $this->yooKassaClient->shouldReceive('createPayment')
            ->once()
            ->with(
                Mockery::on(fn ($params) => $params['amount']['value'] === '100.00' &&
                    $params['amount']['currency'] === 'RUB' &&
                    $params['confirmation']['type'] === 'redirect' &&
                    $params['description'] === 'Test payment' &&
                    $params['capture'] === true
                ),
                'idempotency-key-1'
            )
            ->andReturn($response);

        $result = $this->provider->createPayment(
            paymentId: 'internal-uuid-123',
            amount: Money::ofRub(10000),
            description: 'Test payment',
            returnUrl: 'https://example.com/return',
            idempotencyKey: 'idempotency-key-1',
        );

        $this->assertInstanceOf(ProviderResponse::class, $result);
        $this->assertSame('22d65900-000f-5000-a000-10d000000000', $result->externalId->toString());
        $this->assertSame('pending', $result->status);
        $this->assertSame('https://yookassa.ru/checkout/payments/abc123', $result->confirmationUrl);
    }

    public function test_create_payment_throws_payment_exception_on_api_error(): void
    {
        $this->yooKassaClient->shouldReceive('createPayment')
            ->once()
            ->andThrow(new \RuntimeException('Network error'));

        $this->logger->shouldReceive('error')->once()->with(
            'YooKassa: ошибка создания платежа',
            Mockery::on(fn ($ctx) => str_contains($ctx['error'], 'Network error'))
        );

        $this->expectException(PaymentException::class);
        $this->expectExceptionMessageMatches('/YooKassa createPayment failed/');

        $this->provider->createPayment(
            paymentId: 'internal-uuid-123',
            amount: Money::ofRub(50000),
            description: 'Test',
            returnUrl: 'https://example.com/return',
            idempotencyKey: 'idempotency-key-2',
        );
    }

    public function test_get_payment_returns_provider_response(): void
    {
        $response = Mockery::mock(Payment::class);
        $response->shouldReceive('getId')->andReturn('22d65900-000f-5000-a000-10d000000000');
        $response->shouldReceive('getStatus')->andReturn('succeeded');
        $response->shouldReceive('getPaymentMethod')->andReturnNull()->byDefault();
        $response->shouldReceive('jsonSerialize')->andReturn(['id' => '22d65900-000f-5000-a000-10d000000000', 'status' => 'succeeded']);

        $this->yooKassaClient->shouldReceive('getPaymentInfo')
            ->once()
            ->with('22d65900-000f-5000-a000-10d000000000')
            ->andReturn($response);

        $externalId = ExternalId::fromString('22d65900-000f-5000-a000-10d000000000');
        $result = $this->provider->getPayment($externalId);

        $this->assertInstanceOf(ProviderResponse::class, $result);
        $this->assertSame('succeeded', $result->status);
        $this->assertSame('22d65900-000f-5000-a000-10d000000000', $result->externalId->toString());
    }

    public function test_get_payment_throws_payment_exception_on_api_error(): void
    {
        $this->yooKassaClient->shouldReceive('getPaymentInfo')
            ->once()
            ->andThrow(new \RuntimeException('Payment not found'));

        $this->expectException(PaymentException::class);
        $this->expectExceptionMessageMatches('/YooKassa getPayment failed/');

        $this->provider->getPayment(ExternalId::fromString('nonexistent-id'));
    }

    public function test_refund_payment_returns_provider_response(): void
    {
        $response = Mockery::mock(CreateRefundResponse::class);
        $response->shouldReceive('getId')->andReturn('refund-id-abc');
        $response->shouldReceive('getStatus')->andReturn('succeeded');
        $response->shouldReceive('jsonSerialize')->andReturn(['id' => 'refund-id-abc', 'status' => 'succeeded']);

        $this->yooKassaClient->shouldReceive('createRefund')
            ->once()
            ->with(
                Mockery::on(fn ($params) => $params['payment_id'] === '22d65900-000f-5000-a000-10d000000000' &&
                    $params['amount']['value'] === '100.00' &&
                    $params['amount']['currency'] === 'RUB'
                ),
                Mockery::type('string')
            )
            ->andReturn($response);

        $result = $this->provider->refundPayment(
            externalId: ExternalId::fromString('22d65900-000f-5000-a000-10d000000000'),
            amount: Money::ofRub(10000),
        );

        $this->assertInstanceOf(ProviderResponse::class, $result);
        $this->assertSame('refund-id-abc', $result->externalId->toString());
        $this->assertSame('succeeded', $result->status);
    }

    public function test_refund_payment_throws_payment_exception_on_api_error(): void
    {
        $this->yooKassaClient->shouldReceive('createRefund')
            ->once()
            ->andThrow(new \RuntimeException('Refund not allowed'));

        $this->expectException(PaymentException::class);
        $this->expectExceptionMessageMatches('/YooKassa refund failed/');

        $this->provider->refundPayment(
            externalId: ExternalId::fromString('22d65900-000f-5000-a000-10d000000000'),
            amount: Money::ofRub(10000),
        );
    }

    public function test_verify_webhook_returns_true_for_valid_payload(): void
    {
        $payload = [
            'event' => 'payment.succeeded',
            'object' => ['id' => '22d65900-000f-5000-a000-10d000000000', 'status' => 'succeeded'],
        ];

        // No allowed IPs configured — pass-through to payload check only
        config(['payments.yookassa.webhook_ips' => []]);

        $this->assertTrue($this->provider->verifyWebhook($payload, []));
    }

    public function test_verify_webhook_returns_false_for_missing_event(): void
    {
        config(['payments.yookassa.webhook_ips' => []]);

        $payload = ['object' => ['id' => 'some-id']];

        $this->assertFalse($this->provider->verifyWebhook($payload, []));
    }

    public function test_verify_webhook_returns_false_for_missing_object_id(): void
    {
        config(['payments.yookassa.webhook_ips' => []]);

        $payload = ['event' => 'payment.succeeded', 'object' => []];

        $this->assertFalse($this->provider->verifyWebhook($payload, []));
    }

    public function test_parse_webhook_returns_provider_response(): void
    {
        $payload = [
            'event' => 'payment.succeeded',
            'object' => [
                'id' => '22d65900-000f-5000-a000-10d000000000',
                'status' => 'succeeded',
                'amount' => ['value' => '100.00', 'currency' => 'RUB'],
            ],
        ];

        $result = $this->provider->parseWebhook($payload);

        $this->assertInstanceOf(ProviderResponse::class, $result);
        $this->assertSame('22d65900-000f-5000-a000-10d000000000', $result->externalId->toString());
        $this->assertSame('succeeded', $result->status);
        $this->assertSame($payload, $result->rawData);
    }

    public function test_money_amount_is_correctly_converted_to_rubles(): void
    {
        $confirmation = Mockery::mock(ConfirmationRedirect::class);
        $confirmation->shouldReceive('getConfirmationUrl')->andReturn('https://example.com');

        $response = Mockery::mock(CreatePaymentResponse::class);
        $response->shouldReceive('getId')->andReturn('ext-id');
        $response->shouldReceive('getStatus')->andReturn('pending');
        $response->shouldReceive('getConfirmation')->andReturn($confirmation);
        $response->shouldReceive('getPaymentMethod')->andReturnNull()->byDefault();
        $response->shouldReceive('jsonSerialize')->andReturn([]);

        $this->yooKassaClient->shouldReceive('createPayment')
            ->once()
            ->with(
                Mockery::on(fn ($params) => $params['amount']['value'] === '999.99'),
                Mockery::any()
            )
            ->andReturn($response);

        $this->provider->createPayment(
            paymentId: 'uuid',
            amount: Money::ofRub(99999),
            description: 'Test',
            returnUrl: 'https://example.com',
            idempotencyKey: 'key',
        );
    }
}
