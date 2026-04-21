<?php

declare(strict_types=1);

namespace App\Payments\Infrastructure\Observability;

use App\Payments\Application\DTOs\PaymentResultDTO;
use Illuminate\Support\Facades\Http;

/**
 * Исходящие уведомления (outbound webhooks).
 * Отправляет HTTP POST на notification_url из метаданных платежа
 * после каждого изменения статуса.
 */
class NotificationService
{
    public function __construct(
        private readonly PaymentLogger $logger,
        private readonly MetricsService $metrics,
    ) {}

    /**
     * Отправить уведомление если в метаданных платежа указан notification_url.
     *
     * @param array<string, mixed> $metadata
     */
    public function notify(PaymentResultDTO $payment, array $metadata): void
    {
        $url = (string) ($metadata['notification_url'] ?? '');

        if ($url === '') {
            return;
        }

        $payload = [
            'event'      => 'payment.status_changed',
            'payment_id' => $payment->paymentId,
            'status'     => $payment->status,
            'amount'     => $payment->amount,
            'currency'   => $payment->currency,
            'external_id' => $payment->externalId,
        ];

        try {
            $response = Http::timeout(10)
                ->withHeader('X-Signature', $this->sign($payload))
                ->post($url, $payload);

            $success = $response->successful();

            $this->logger->info('Outbound notification sent', [
                'payment_id' => $payment->paymentId,
                'url'        => $url,
                'status'     => $response->status(),
            ]);

            $this->metrics->notificationSent($success);
        } catch (\Throwable $e) {
            $this->logger->warning('Outbound notification failed', [
                'payment_id' => $payment->paymentId,
                'url'        => $url,
                'error'      => $e->getMessage(),
            ]);

            $this->metrics->notificationSent(false);
        }
    }

    /**
     * Подпись для верификации на стороне клиента.
     * Клиент может проверить: HMAC-SHA256(json_body, APP_KEY)
     *
     * @param array<string, mixed> $payload
     */
    private function sign(array $payload): string
    {
        return hash_hmac('sha256', json_encode($payload) ?: '', config('app.key'));
    }
}
