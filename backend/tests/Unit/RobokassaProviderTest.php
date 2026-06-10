<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Payments\Domain\Contracts\ProviderResponse;
use App\Payments\Domain\Exceptions\PaymentException;
use App\Payments\Domain\ValueObjects\ExternalId;
use App\Payments\Domain\ValueObjects\Money;
use App\Payments\Infrastructure\Observability\PaymentLogger;
use App\Payments\Infrastructure\Providers\RobokassaProvider;
use Illuminate\Support\Facades\Http;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class RobokassaProviderTest extends TestCase
{
    private RobokassaProvider $provider;

    private MockInterface $logger;

    private string $login = 'test_merchant';

    private string $password1 = 'test_pass1';

    private string $password2 = 'test_pass2';

    protected function setUp(): void
    {
        parent::setUp();

        $this->logger = Mockery::mock(PaymentLogger::class);
        $this->logger->shouldReceive('info')->andReturnNull()->byDefault();
        $this->logger->shouldReceive('warning')->andReturnNull()->byDefault();
        $this->logger->shouldReceive('error')->andReturnNull()->byDefault();

        $this->provider = new RobokassaProvider(
            login: $this->login,
            password1: $this->password1,
            password2: $this->password2,
            isTest: true,
            logger: $this->logger,
        );
    }

    public function test_name_returns_robokassa(): void
    {
        $this->assertSame('robokassa', $this->provider->name());
    }

    public function test_create_payment_returns_redirect_url(): void
    {
        $result = $this->provider->createPayment(
            paymentId: 'internal-pay-001',
            amount: Money::ofRub(30000), // 300 RUB
            description: 'Test Robokassa payment',
            returnUrl: 'https://example.com/return',
            idempotencyKey: 'idem-key-1',
        );

        $this->assertInstanceOf(ProviderResponse::class, $result);
        $this->assertSame('pending', $result->status);
        $this->assertStringContainsString('https://auth.robokassa.ru/Merchant/Index.aspx', $result->confirmationUrl);
        $this->assertStringContainsString('MerchantLogin=test_merchant', $result->confirmationUrl);
        $this->assertStringContainsString('OutSum=300.00', $result->confirmationUrl);
        $this->assertStringContainsString('Shp_paymentId=internal-pay-001', $result->confirmationUrl);
        $this->assertStringContainsString('IsTest=1', $result->confirmationUrl);
    }

    public function test_create_payment_uses_internal_id_as_placeholder_external_id(): void
    {
        $result = $this->provider->createPayment(
            paymentId: 'internal-pay-xyz',
            amount: Money::ofRub(10000),
            description: 'Test',
            returnUrl: 'https://example.com',
            idempotencyKey: 'idem-key',
        );

        $this->assertSame('internal-pay-xyz', $result->externalId->toString());
    }

    public function test_create_payment_signature_is_correct(): void
    {
        $paymentId = 'pay-sig-test';
        $amount = Money::ofRub(50000); // 500.00 RUB
        $outSum = '500.00';
        $invId = 0;

        $expectedSig = md5("{$this->login}:{$outSum}:{$invId}:{$this->password1}:Shp_paymentId={$paymentId}");

        $result = $this->provider->createPayment(
            paymentId: $paymentId,
            amount: $amount,
            description: 'Test',
            returnUrl: 'https://example.com',
            idempotencyKey: 'key',
        );

        $this->assertStringContainsString("SignatureValue={$expectedSig}", $result->confirmationUrl);
    }

    public function test_create_payment_truncates_description_to_100_chars(): void
    {
        $longDesc = str_repeat('а', 150);

        $result = $this->provider->createPayment(
            paymentId: 'pay-001',
            amount: Money::ofRub(10000),
            description: $longDesc,
            returnUrl: 'https://example.com',
            idempotencyKey: 'key',
        );

        $encoded = urlencode(mb_substr($longDesc, 0, 100));
        $this->assertStringContainsString("Description={$encoded}", $result->confirmationUrl);
    }

    public function test_get_payment_throws_501(): void
    {
        $this->expectException(PaymentException::class);
        $this->expectExceptionCode(501);
        $this->expectExceptionMessageMatches('/polling|webhooks/i');

        $this->provider->getPayment(ExternalId::fromString('42'));
    }

    public function test_refund_payment_sends_correct_request(): void
    {
        Http::fake([
            '*/Payment/Return' => Http::response('OK', 200),
        ]);

        $result = $this->provider->refundPayment(
            externalId: ExternalId::fromString('42'),
            amount: Money::ofRub(30000), // 300.00 RUB
        );

        $this->assertSame('succeeded', $result->status);

        Http::assertSent(fn ($req) => str_contains($req->url(), '/Payment/Return') &&
            $req['MerchantLogin'] === $this->login &&
            $req['InvoiceID'] === '42' &&
            $req['Amount'] === '300.00'
        );
    }

    public function test_refund_payment_throws_on_http_error(): void
    {
        Http::fake([
            '*/Payment/Return' => Http::response('Error', 500),
        ]);

        $this->expectException(PaymentException::class);
        $this->expectExceptionMessageMatches('/Robokassa refund failed/');

        $this->provider->refundPayment(
            externalId: ExternalId::fromString('42'),
            amount: Money::ofRub(30000),
        );
    }

    public function test_verify_webhook_returns_true_with_valid_signature_and_no_ip_filter(): void
    {
        config(['payments.robokassa.webhook_ips' => []]);

        $paymentId = 'pay-001';
        $outSum = '300.00';
        $invId = '42';
        $signature = strtoupper(md5("{$outSum}:{$invId}:{$this->password2}:Shp_paymentId={$paymentId}"));

        $result = $this->provider->verifyWebhook([
            'OutSum' => $outSum,
            'InvId' => $invId,
            'Shp_paymentId' => $paymentId,
            'SignatureValue' => $signature,
        ], []);

        $this->assertTrue($result);
    }

    public function test_verify_webhook_returns_false_with_invalid_signature(): void
    {
        config(['payments.robokassa.webhook_ips' => []]);

        $this->logger->shouldReceive('warning')->once();

        $result = $this->provider->verifyWebhook([
            'OutSum' => '300.00',
            'InvId' => '42',
            'Shp_paymentId' => 'pay-001',
            'SignatureValue' => 'INVALIDSIG',
        ], []);

        $this->assertFalse($result);
    }

    public function test_verify_webhook_returns_false_when_shp_payment_id_missing(): void
    {
        config(['payments.robokassa.webhook_ips' => []]);

        $this->logger->shouldReceive('warning')->once();

        $result = $this->provider->verifyWebhook([
            'OutSum' => '300.00',
            'InvId' => '42',
            'SignatureValue' => 'ANYSIG',
        ], []);

        $this->assertFalse($result);
    }

    public function test_verify_webhook_blocks_disallowed_ip(): void
    {
        config(['payments.robokassa.webhook_ips' => ['185.26.103.0/24']]);

        $this->logger->shouldReceive('warning')->once();

        // Тестовые запросы идут с 127.0.0.1 — не в диапазоне
        $result = $this->provider->verifyWebhook(['OutSum' => '100', 'InvId' => '1', 'Shp_paymentId' => 'x', 'SignatureValue' => 'x'], []);

        $this->assertFalse($result);
    }

    public function test_parse_webhook_returns_succeeded_response(): void
    {
        $result = $this->provider->parseWebhook([
            'Shp_paymentId' => 'pay-001',
            'InvId' => '42',
            'OutSum' => '300.00',
        ]);

        $this->assertSame('succeeded', $result->status);
        $this->assertSame('pay-001', $result->externalId->toString());
    }

    public function test_parse_webhook_includes_inv_id_in_raw_data(): void
    {
        $result = $this->provider->parseWebhook([
            'Shp_paymentId' => 'pay-001',
            'InvId' => '42',
            'OutSum' => '300.00',
        ]);

        $this->assertSame('42', $result->rawData['inv_id'] ?? null);
        $this->assertSame('300.00', $result->rawData['out_sum'] ?? null);
    }

    public function test_kopecks_are_converted_to_rubles_correctly(): void
    {
        $result = $this->provider->createPayment(
            paymentId: 'pay-001',
            amount: Money::ofRub(99999), // 999.99 RUB
            description: 'Test',
            returnUrl: 'https://example.com',
            idempotencyKey: 'key',
        );

        $this->assertStringContainsString('OutSum=999.99', $result->confirmationUrl);
    }
}
