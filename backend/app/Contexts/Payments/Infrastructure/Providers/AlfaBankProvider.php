<?php

declare(strict_types=1);

namespace App\Contexts\Payments\Infrastructure\Providers;

use App\Contexts\Payments\Application\DTOs\CreatePaymentOptionsDTO;
use App\Contexts\Payments\Domain\Contracts\PaymentProviderInterface;
use App\Contexts\Payments\Domain\Contracts\ProviderResponse;
use App\Contexts\Payments\Domain\Exceptions\PaymentException;
use App\Contexts\Payments\Domain\ValueObjects\ExternalId;
use App\Contexts\Payments\Domain\ValueObjects\Money;
use App\Contexts\Payments\Infrastructure\Observability\PaymentLogger;
use Illuminate\Support\Facades\Http;

final readonly class AlfaBankProvider implements PaymentProviderInterface
{
    /** @param array<string> $webhookIps */
    public function __construct(
        private string $login,
        private string $password,
        private string $baseUrl,
        private PaymentLogger $logger,
        private array $webhookIps = [],
    ) {}

    public function name(): string
    {
        return 'alfabank';
    }

    public function createPayment(
        string $paymentId,
        Money $amount,
        string $description,
        string $returnUrl,
        string $idempotencyKey,
        CreatePaymentOptionsDTO $options = new CreatePaymentOptionsDTO,
    ): ProviderResponse {
        $this->logger->info('Альфа-Банк: регистрация заказа', [
            'payment_id' => $paymentId,
            'amount' => $amount->amount(),
        ]);

        $response = retry(
            times: 3,
            callback: fn () => Http::asForm()->post("{$this->baseUrl}/register.do", [
                'userName' => $this->login,
                'password' => $this->password,
                'orderNumber' => $paymentId,
                'amount' => $amount->amount(),
                'returnUrl' => $returnUrl,
                'description' => mb_substr($description, 0, 512),
                'jsonParams' => json_encode(['internal_payment_id' => $paymentId]),
            ]),
            sleepMilliseconds: 500,
            when: fn (\Throwable $e): bool => $this->isRetryable($e),
        );

        if (! $response->successful()) {
            throw new PaymentException("Альфа-Банк: HTTP ошибка [{$response->status()}]");
        }

        $data = $response->json();

        $errorCode = (string) ($data['errorCode'] ?? '');
        if ($errorCode !== '' && $errorCode !== '0') {
            $errorMessage = (string) ($data['errorMessage'] ?? 'Unknown error');
            throw new PaymentException("Альфа-Банк: {$errorMessage} (код {$errorCode})");
        }

        $orderId = $data['orderId'] ?? throw new PaymentException('Альфа-Банк: ответ не содержит orderId');
        $formUrl = $data['formUrl'] ?? '';

        $this->logger->info('Альфа-Банк: заказ зарегистрирован', [
            'payment_id' => $paymentId,
            'order_id' => $orderId,
        ]);

        return new ProviderResponse(
            externalId: ExternalId::fromString($orderId),
            confirmationUrl: $formUrl,
            status: 'pending',
            rawData: $data,
        );
    }

    public function getPayment(ExternalId $externalId): ProviderResponse
    {
        $response = Http::asForm()->post("{$this->baseUrl}/getOrderStatusExtended.do", [
            'userName' => $this->login,
            'password' => $this->password,
            'orderId' => $externalId->toString(),
        ]);

        if (! $response->successful()) {
            throw new PaymentException("Альфа-Банк: ошибка запроса статуса [{$response->status()}]");
        }

        $data = $response->json();

        return new ProviderResponse(
            externalId: $externalId,
            confirmationUrl: '',
            status: $this->mapOrderStatus((int) ($data['orderStatus'] ?? -1)),
            rawData: $data,
        );
    }

    public function refundPayment(ExternalId $externalId, Money $amount): ProviderResponse
    {
        $response = retry(
            times: 3,
            callback: fn () => Http::asForm()->post("{$this->baseUrl}/refund.do", [
                'userName' => $this->login,
                'password' => $this->password,
                'orderId' => $externalId->toString(),
                'amount' => $amount->amount(),
            ]),
            sleepMilliseconds: 500,
            when: fn (\Throwable $e): bool => $this->isRetryable($e),
        );

        if (! $response->successful()) {
            throw new PaymentException("Альфа-Банк: HTTP ошибка возврата [{$response->status()}]");
        }

        $data = $response->json();

        $errorCode = (string) ($data['errorCode'] ?? '');
        if ($errorCode !== '' && $errorCode !== '0') {
            $errorMessage = (string) ($data['errorMessage'] ?? 'Unknown error');
            throw new PaymentException("Альфа-Банк: ошибка возврата: {$errorMessage} (код {$errorCode})");
        }

        $this->logger->info('Альфа-Банк: возврат выполнен', [
            'order_id' => $externalId->toString(),
            'amount' => $amount->amount(),
        ]);

        return new ProviderResponse(
            externalId: $externalId,
            confirmationUrl: '',
            status: 'succeeded',
            rawData: $data,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, list<string|null>>  $headers
     */
    public function verifyWebhook(array $payload, array $headers): bool
    {
        if ($this->webhookIps !== []) {
            $requestIp = request()->ip();

            if (! $this->ipInAllowedRanges($requestIp, $this->webhookIps)) {
                $this->logger->warning('Альфа-Банк: webhook с неизвестного IP', ['ip' => $requestIp]);

                return false;
            }
        }

        // Альфа-Банк не присылает криптографическую подпись.
        // Минимальная проверка: ожидаемые поля присутствуют.
        return isset($payload['mdOrder'], $payload['operation']);
    }

    /** @param array<string, mixed> $payload */
    public function parseWebhook(array $payload): ProviderResponse
    {
        $mdOrder = (string) ($payload['mdOrder'] ?? '');
        $operation = (string) ($payload['operation'] ?? '');

        return new ProviderResponse(
            externalId: ExternalId::fromString($mdOrder),
            confirmationUrl: '',
            status: $this->mapOperation($operation),
            rawData: $payload,
        );
    }

    // ─── Private helpers ─────────────────────────────────────────────────────

    private function mapOrderStatus(int $status): string
    {
        return match ($status) {
            2 => 'succeeded',
            3 => 'canceled',
            6 => 'refunded',
            default => 'pending',
        };
    }

    private function mapOperation(string $operation): string
    {
        return match ($operation) {
            'deposited' => 'succeeded',
            'refunded' => 'refunded',
            'reversed',
            'declinedByTimeout' => 'canceled',
            default => 'pending',
        };
    }

    private function isRetryable(\Throwable $e): bool
    {
        if ($e->getCode() >= 500) {
            return true;
        }

        return str_contains($e->getMessage(), 'cURL error') ||
               str_contains($e->getMessage(), 'timed out');
    }

    /** @param array<string> $cidrs */
    private function ipInAllowedRanges(string $ip, array $cidrs): bool
    {
        $ipLong = ip2long($ip);

        if ($ipLong === false) {
            return false;
        }

        foreach ($cidrs as $cidr) {
            if (! str_contains($cidr, '/')) {
                if (ip2long($cidr) === $ipLong) {
                    return true;
                }

                continue;
            }

            [$network, $prefix] = explode('/', $cidr, 2);
            $mask = ~((1 << (32 - (int) $prefix)) - 1);

            if (($ipLong & $mask) === (ip2long($network) & $mask)) {
                return true;
            }
        }

        return false;
    }
}
