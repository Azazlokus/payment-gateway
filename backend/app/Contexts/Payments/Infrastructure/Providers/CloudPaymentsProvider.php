<?php

declare(strict_types=1);

namespace App\Contexts\Payments\Infrastructure\Providers;

use App\Contexts\Payments\Application\DTOs\CreatePaymentOptionsDTO;
use App\Contexts\Payments\Domain\Contracts\PaymentProviderInterface;
use App\Contexts\Payments\Domain\Contracts\ProviderResponse;
use App\Contexts\Payments\Domain\Contracts\SupportsTokenization;
use App\Contexts\Payments\Domain\Contracts\TokenizationResult;
use App\Contexts\Payments\Domain\Exceptions\PaymentException;
use App\Contexts\Payments\Domain\ValueObjects\ExternalId;
use App\Contexts\Payments\Domain\ValueObjects\Money;
use App\Contexts\Payments\Infrastructure\Observability\PaymentLogger;
use Illuminate\Support\Facades\Http;

final class CloudPaymentsProvider implements PaymentProviderInterface, SupportsTokenization
{
    private const BASE_URL = 'https://api.cloudpayments.ru';

    public function __construct(
        private readonly string $publicId,
        private readonly string $apiSecret,
        private readonly PaymentLogger $logger,
    ) {}

    public function name(): string
    {
        return 'cloudpayments';
    }

    public function createPayment(
        string $paymentId,
        Money $amount,
        string $description,
        string $returnUrl,
        string $idempotencyKey,
        CreatePaymentOptionsDTO $options = new CreatePaymentOptionsDTO,
    ): ProviderResponse {
        $this->logger->info('CloudPayments: создание ссылки на оплату', [
            'payment_id' => $paymentId,
            'amount' => $amount->amount(),
        ]);

        $response = retry(
            times: 3,
            callback: fn () => Http::withBasicAuth($this->publicId, $this->apiSecret)
                ->post(self::BASE_URL.'/payments/link/create', [
                    'Amount' => $this->kopecksToRubles($amount->amount()),
                    'Currency' => $amount->currency()->value,
                    'Description' => mb_substr($description, 0, 255),
                    'InvoiceId' => $paymentId,
                    'SuccessRedirectUrl' => $returnUrl,
                    'FailRedirectUrl' => $returnUrl,
                ]),
            sleepMilliseconds: 500,
            when: fn (\Throwable $e) => $this->isRetryable($e),
        );

        if (! $response->successful()) {
            throw new PaymentException("CloudPayments: HTTP ошибка [{$response->status()}]");
        }

        $data = $response->json();

        if (! ($data['Success'] ?? false)) {
            $message = $data['Message'] ?? 'Unknown error';
            throw new PaymentException("CloudPayments: {$message}");
        }

        $paymentUrl = $data['Model']['Url'] ?? throw new PaymentException('CloudPayments: ответ не содержит Url');
        $transactionId = (string) ($data['Model']['TransactionId'] ?? $paymentId);

        $this->logger->info('CloudPayments: ссылка создана', [
            'payment_id' => $paymentId,
            'transaction_id' => $transactionId,
        ]);

        return new ProviderResponse(
            externalId: ExternalId::fromString($transactionId),
            confirmationUrl: $paymentUrl,
            status: 'pending',
            rawData: $data['Model'] ?? [],
        );
    }

    public function getPayment(ExternalId $externalId): ProviderResponse
    {
        // CloudPayments: поиск по InvoiceId (наш paymentId хранится в externalId до первого вебхука)
        $response = Http::withBasicAuth($this->publicId, $this->apiSecret)
            ->post(self::BASE_URL.'/payments/find', [
                'InvoiceId' => $externalId->toString(),
            ]);

        if (! $response->successful()) {
            throw new PaymentException("CloudPayments: ошибка запроса статуса [{$response->status()}]");
        }

        $data = $response->json();
        $model = $data['Model'] ?? [];

        return new ProviderResponse(
            externalId: ExternalId::fromString((string) ($model['TransactionId'] ?? $externalId->toString())),
            confirmationUrl: '',
            status: $this->mapStatus((string) ($model['Status'] ?? '')),
            rawData: $model,
        );
    }

    public function refundPayment(ExternalId $externalId, Money $amount): ProviderResponse
    {
        $response = retry(
            times: 3,
            callback: fn () => Http::withBasicAuth($this->publicId, $this->apiSecret)
                ->post(self::BASE_URL.'/payments/refund', [
                    'TransactionId' => $externalId->toString(),
                    'Amount' => $this->kopecksToRubles($amount->amount()),
                ]),
            sleepMilliseconds: 500,
            when: fn (\Throwable $e) => $this->isRetryable($e),
        );

        if (! $response->successful()) {
            throw new PaymentException("CloudPayments: HTTP ошибка возврата [{$response->status()}]");
        }

        $data = $response->json();

        if (! ($data['Success'] ?? false)) {
            throw new PaymentException('CloudPayments: ошибка возврата: '.($data['Message'] ?? 'Unknown error'));
        }

        $this->logger->info('CloudPayments: возврат выполнен', [
            'transaction_id' => $externalId->toString(),
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
        $body = request()->getContent();
        $received = $headers['content-hmac'][0] ?? ($headers['Content-HMAC'][0] ?? '');
        $expected = base64_encode(hash_hmac('sha256', $body, $this->apiSecret, true));

        if (! hash_equals($expected, $received)) {
            $this->logger->warning('CloudPayments: невалидная подпись вебхука');

            return false;
        }

        return isset($payload['TransactionId'], $payload['Status']);
    }

    /** @param array<string, mixed> $payload */
    public function parseWebhook(array $payload): ProviderResponse
    {
        $transactionId = (string) ($payload['TransactionId'] ?? '');
        $status = $this->mapStatus((string) ($payload['Status'] ?? ''));

        $kopecks = isset($payload['Amount'])
            ? (int) round((float) $payload['Amount'] * 100)
            : null;

        return new ProviderResponse(
            externalId: ExternalId::fromString($transactionId),
            confirmationUrl: '',
            status: $status,
            refundAmountKopecks: $status === 'refunded' ? $kopecks : null,
            rawData: $payload,
        );
    }

    // ─── SupportsTokenization ──────────────────────────────────────────────

    public function tokenize(string $paymentId): TokenizationResult
    {
        $response = Http::withBasicAuth($this->publicId, $this->apiSecret)
            ->post(self::BASE_URL.'/payments/find', ['InvoiceId' => $paymentId]);

        if (! $response->successful()) {
            throw new PaymentException("CloudPayments: tokenize lookup failed [{$response->status()}]");
        }

        $model = $response->json('Model') ?? [];
        $token = (string) ($model['Token'] ?? '');

        if ($token === '') {
            throw new PaymentException('CloudPayments: no token returned for this payment');
        }

        return new TokenizationResult(
            token: $token,
            type: 'card',
            last4: (string) ($model['CardLastFour'] ?? '0000'),
            brand: (string) ($model['CardType'] ?? 'unknown'),
            expiresAt: (string) ($model['CardExpDate'] ?? ''),
        );
    }

    public function chargeToken(
        string $token,
        Money $amount,
        string $description,
        string $idempotencyKey,
    ): ProviderResponse {
        $this->logger->info('CloudPayments: рекуррентное списание', [
            'amount' => $amount->amount(),
        ]);

        $response = retry(
            times: 3,
            callback: fn () => Http::withBasicAuth($this->publicId, $this->apiSecret)
                ->post(self::BASE_URL.'/payments/tokens/charge', [
                    'Amount' => $this->kopecksToRubles($amount->amount()),
                    'Currency' => $amount->currency()->value,
                    'AccountId' => $idempotencyKey,
                    'Token' => $token,
                    'Description' => mb_substr($description, 0, 255),
                ]),
            sleepMilliseconds: 500,
            when: fn (\Throwable $e) => $this->isRetryable($e),
        );

        if (! $response->successful()) {
            throw new PaymentException("CloudPayments: token charge failed [{$response->status()}]");
        }

        $data = $response->json();

        if (! ($data['Success'] ?? false)) {
            throw new PaymentException('CloudPayments: token charge error: '.($data['Message'] ?? 'Unknown'));
        }

        $transactionId = (string) ($data['Model']['TransactionId'] ?? '');

        return new ProviderResponse(
            externalId: ExternalId::fromString($transactionId),
            confirmationUrl: '',
            status: 'succeeded',
            rawData: $data['Model'] ?? [],
        );
    }

    public function deleteToken(string $token): void
    {
        // CloudPayments does not expose a token deletion API.
        $this->logger->info('CloudPayments: token deletion requested (no-op)');
    }

    // ─── Private helpers ─────────────────────────────────────────────────────

    private function mapStatus(string $status): string
    {
        return match ($status) {
            'Completed', 'Authorized' => 'succeeded',
            'Cancelled', 'Declined' => 'canceled',
            'Refunded' => 'refunded',
            default => 'pending',
        };
    }

    private function kopecksToRubles(int $kopecks): float
    {
        return round($kopecks / 100, 2);
    }

    private function isRetryable(\Throwable $e): bool
    {
        if ($e->getCode() >= 500) {
            return true;
        }

        return str_contains($e->getMessage(), 'cURL error') ||
               str_contains($e->getMessage(), 'timed out');
    }
}
