<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Payments\Application\DTOs\CreatePaymentOptionsDTO;
use App\Payments\Domain\Contracts\PaymentProviderInterface;
use App\Payments\Domain\ValueObjects\ExternalId;
use App\Payments\Domain\ValueObjects\Money;
use App\Payments\Infrastructure\Observability\PaymentLogger;
use App\Payments\Infrastructure\Providers\AlfaBankProvider;
use App\Payments\Infrastructure\Providers\CloudPaymentsProvider;
use App\Payments\Infrastructure\Providers\RobokassaProvider;
use App\Payments\Infrastructure\Providers\SbpProvider;
use App\Payments\Infrastructure\Providers\YooKassaProvider;
use Mockery;
use Tests\TestCase;
use YooKassa\Client;

class ProviderContractTest extends TestCase
{
    /** @return array<string, array{PaymentProviderInterface, string}> */
    public static function providerDataset(): array
    {
        $logger = Mockery::mock(PaymentLogger::class);
        $logger->shouldReceive('info', 'warning', 'error')->andReturnNull()->byDefault();

        $yooClient = Mockery::mock(Client::class);
        $yooClient->shouldReceive('setAuth')->andReturnSelf()->byDefault();

        return [
            'yookassa' => [
                new YooKassaProvider('shop-1', 'secret', $logger, $yooClient),
                'yookassa',
            ],
            'robokassa' => [
                new RobokassaProvider('login', 'pass1', 'pass2', true, $logger),
                'robokassa',
            ],
            'cloudpayments' => [
                new CloudPaymentsProvider('pk_test', 'api_secret', $logger),
                'cloudpayments',
            ],
            'sbp' => [
                new SbpProvider('merchant-1', 'api-key', 'webhook-secret', 'https://api.nspk.ru', $logger),
                'sbp',
            ],
            'alfabank' => [
                new AlfaBankProvider('login', 'password', 'https://pay.alfabank.ru/payment/rest', $logger),
                'alfabank',
            ],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerDataset')]
    public function test_implements_provider_interface(PaymentProviderInterface $provider, string $name): void
    {
        $this->assertInstanceOf(PaymentProviderInterface::class, $provider);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerDataset')]
    public function test_name_returns_non_empty_string(PaymentProviderInterface $provider, string $expectedName): void
    {
        $this->assertSame($expectedName, $provider->name());
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerDataset')]
    public function test_name_is_lowercase_alphanumeric(PaymentProviderInterface $provider, string $name): void
    {
        $this->assertMatchesRegularExpression('/^[a-z0-9_]+$/', $provider->name());
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerDataset')]
    public function test_verify_webhook_returns_bool(PaymentProviderInterface $provider, string $name): void
    {
        $result = $provider->verifyWebhook([], []);
        $this->assertIsBool($result);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerDataset')]
    public function test_parse_webhook_returns_provider_response(PaymentProviderInterface $provider, string $name): void
    {
        $payload = match ($name) {
            'yookassa'      => ['object' => ['id' => 'ext-1', 'status' => 'succeeded', 'amount' => ['value' => '100.00']]],
            'robokassa'     => ['Shp_paymentId' => 'pay-1', 'InvId' => '42', 'OutSum' => '100.00'],
            'cloudpayments' => ['TransactionId' => '12345', 'Status' => 'Completed'],
            'sbp'           => ['qrId' => 'QR-1', 'status' => 'PAID'],
            'alfabank'      => ['mdOrder' => 'order-1', 'operation' => 'deposited'],
            default         => [],
        };

        $response = $provider->parseWebhook($payload);

        $this->assertInstanceOf(\App\Payments\Domain\Contracts\ProviderResponse::class, $response);
        $this->assertNotEmpty($response->status);
        $this->assertInstanceOf(ExternalId::class, $response->externalId);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerDataset')]
    public function test_parse_webhook_status_is_known_value(PaymentProviderInterface $provider, string $name): void
    {
        $payload = match ($name) {
            'yookassa'      => ['object' => ['id' => 'ext-1', 'status' => 'pending', 'amount' => ['value' => '100.00']]],
            'robokassa'     => ['Shp_paymentId' => 'pay-1', 'InvId' => '42', 'OutSum' => '100.00'],
            'cloudpayments' => ['TransactionId' => '1', 'Status' => 'Created'],
            'sbp'           => ['qrId' => 'QR-1', 'status' => 'IN_PROGRESS'],
            'alfabank'      => ['mdOrder' => 'order-1', 'operation' => 'unknown'],
            default         => [],
        };

        $response = $provider->parseWebhook($payload);

        $this->assertContains($response->status, ['pending', 'succeeded', 'canceled', 'refunded']);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerDataset')]
    public function test_parse_webhook_maps_success_status(PaymentProviderInterface $provider, string $name): void
    {
        $payload = match ($name) {
            'yookassa'      => ['object' => ['id' => 'ext-1', 'status' => 'succeeded', 'amount' => ['value' => '500.00']]],
            'robokassa'     => ['Shp_paymentId' => 'pay-1', 'InvId' => '42', 'OutSum' => '500.00'],
            'cloudpayments' => ['TransactionId' => '1', 'Status' => 'Completed'],
            'sbp'           => ['qrId' => 'QR-1', 'status' => 'PAID'],
            'alfabank'      => ['mdOrder' => 'order-1', 'operation' => 'deposited'],
            default         => [],
        };

        $response = $provider->parseWebhook($payload);

        $this->assertSame('succeeded', $response->status);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerDataset')]
    public function test_parse_webhook_maps_cancel_status(PaymentProviderInterface $provider, string $name): void
    {
        $payload = match ($name) {
            'yookassa'      => ['object' => ['id' => 'ext-1', 'status' => 'canceled', 'amount' => ['value' => '100.00']]],
            'robokassa'     => ['Shp_paymentId' => 'pay-1', 'InvId' => '42', 'OutSum' => '100.00', '_force_canceled' => true],
            'cloudpayments' => ['TransactionId' => '1', 'Status' => 'Cancelled'],
            'sbp'           => ['qrId' => 'QR-1', 'status' => 'CANCELLED'],
            'alfabank'      => ['mdOrder' => 'order-1', 'operation' => 'reversed'],
            default         => [],
        };

        // Robokassa всегда возвращает succeeded (платёж уже оплачен при получении ResultURL)
        if ($name === 'robokassa') {
            $this->markTestSkipped('Robokassa ResultURL не содержит статус отмены');
        }

        $response = $provider->parseWebhook($payload);

        $this->assertSame('canceled', $response->status);
    }
}
