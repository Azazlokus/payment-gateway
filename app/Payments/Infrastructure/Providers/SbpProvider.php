<?php

declare(strict_types=1);

namespace App\Payments\Infrastructure\Providers;

use App\Payments\Application\DTOs\CreatePaymentOptionsDTO;
use App\Payments\Domain\Contracts\PaymentProviderInterface;
use App\Payments\Domain\Contracts\ProviderResponse;
use App\Payments\Domain\Exceptions\PaymentException;
use App\Payments\Domain\ValueObjects\ExternalId;
use App\Payments\Domain\ValueObjects\Money;
use App\Payments\Infrastructure\Observability\PaymentLogger;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

final class SbpProvider implements PaymentProviderInterface
{
    public function __construct(
        private readonly string $merchantId,
        private readonly string $apiKey,
        private readonly string $webhookSecret,
        private readonly string $baseUrl,
        private readonly PaymentLogger $logger,
    ) {}

    public function name(): string
    {
        return 'sbp';
    }

    public function createPayment(
        string $paymentId,
        Money $amount,
        string $description,
        string $returnUrl,
        string $idempotencyKey,
        CreatePaymentOptionsDTO $options = new CreatePaymentOptionsDTO,
    ): ProviderResponse {
        $this->logger->info('СБП: создание QR-платежа', [
            'payment_id' => $paymentId,
            'amount'     => $amount->amount(),
        ]);

        $response = retry(
            times: 3,
            callback: fn () => Http::withToken($this->apiKey)
                ->post("{$this->baseUrl}/qrc/dynamic", [
                    'merchantId'  => $this->merchantId,
                    'amount'      => ['value' => $amount->amount(), 'currency' => $amount->currency()->value],
                    'order'       => $paymentId,
                    'description' => mb_substr($description, 0, 140),
                    'redirectUrl' => $returnUrl,
                ]),
            sleepMilliseconds: 500,
            when: fn (\Throwable $e) => $this->isRetryable($e),
        );

        if (! $response->successful()) {
            throw new PaymentException("СБП: ошибка создания QR [{$response->status()}]: {$response->body()}");
        }

        $data  = $response->json();
        $qrId  = $data['qrId'] ?? throw new PaymentException('СБП: ответ не содержит qrId');
        $qrUrl = $data['payload'] ?? '';

        $this->logger->info('СБП: QR создан', ['payment_id' => $paymentId, 'qr_id' => $qrId]);

        return new ProviderResponse(
            externalId:      ExternalId::fromString($qrId),
            confirmationUrl: $qrUrl,
            status:          'pending',
            rawData:         $data,
        );
    }

    public function getPayment(ExternalId $externalId): ProviderResponse
    {
        $response = Http::withToken($this->apiKey)
            ->get("{$this->baseUrl}/qrc/{$externalId->toString()}/status");

        if (! $response->successful()) {
            throw new PaymentException("СБП: ошибка запроса статуса [{$response->status()}]: {$response->body()}");
        }

        $data = $response->json();

        return new ProviderResponse(
            externalId:      $externalId,
            confirmationUrl: '',
            status:          $this->mapQrStatus($data['qrStatus'] ?? 'UNKNOWN'),
            rawData:         $data,
        );
    }

    public function refundPayment(ExternalId $externalId, Money $amount): ProviderResponse
    {
        $response = retry(
            times: 3,
            callback: fn () => Http::withToken($this->apiKey)
                ->post("{$this->baseUrl}/refund", [
                    'qrId'     => $externalId->toString(),
                    'amount'   => ['value' => $amount->amount(), 'currency' => $amount->currency()->value],
                    'refundId' => (string) Str::uuid(),
                ]),
            sleepMilliseconds: 500,
            when: fn (\Throwable $e) => $this->isRetryable($e),
        );

        if (! $response->successful()) {
            throw new PaymentException("СБП: ошибка возврата [{$response->status()}]: {$response->body()}");
        }

        $data   = $response->json();
        $status = $data['refundStatus'] ?? '';

        if ($status === 'DECLINED') {
            throw new PaymentException("СБП: возврат отклонён: {$response->body()}");
        }

        $this->logger->info('СБП: возврат создан', [
            'qr_id'  => $externalId->toString(),
            'amount' => $amount->amount(),
        ]);

        return new ProviderResponse(
            externalId:      $externalId,
            confirmationUrl: '',
            status:          'succeeded',
            rawData:         $data,
        );
    }

    /**
     * @param array<string, mixed>  $payload
     * @param array<string, string> $headers
     */
    public function verifyWebhook(array $payload, array $headers): bool
    {
        $received = $headers['x-api-key'][0] ?? ($headers['X-Api-Key'][0] ?? '');

        if (! hash_equals($this->webhookSecret, $received)) {
            $this->logger->warning('СБП: невалидный X-Api-Key вебхука');

            return false;
        }

        return isset($payload['qrId'], $payload['status']);
    }

    /** @param array<string, mixed> $payload */
    public function parseWebhook(array $payload): ProviderResponse
    {
        $qrId = (string) ($payload['qrId'] ?? '');

        return new ProviderResponse(
            externalId:      ExternalId::fromString($qrId),
            confirmationUrl: '',
            status:          $this->mapQrStatus($payload['status'] ?? 'UNKNOWN'),
            rawData:         $payload,
        );
    }

    // ─── Private helpers ─────────────────────────────────────────────────────

    private function mapQrStatus(string $qrStatus): string
    {
        return match (strtoupper($qrStatus)) {
            'PAID'                  => 'succeeded',
            'EXPIRED', 'CANCELLED'  => 'canceled',
            default                 => 'pending',
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
}
