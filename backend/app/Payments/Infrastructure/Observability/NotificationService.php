<?php

declare(strict_types=1);

namespace App\Payments\Infrastructure\Observability;

use App\Payments\Application\DTOs\PaymentResultDTO;
use App\Payments\Infrastructure\Persistence\Models\OutboundWebhookLog;
use Illuminate\Support\Facades\Http;

/**
 * Исходящие уведомления (outbound webhooks).
 * Отправляет HTTP POST на notification_url из метаданных платежа
 * после каждого изменения статуса. Каждая попытка пишется в outbound_webhook_logs.
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
            'event'       => 'payment.status_changed',
            'payment_id'  => $payment->paymentId,
            'status'      => $payment->status,
            'amount'      => $payment->amount,
            'currency'    => $payment->currency,
            'external_id' => $payment->externalId,
        ];

        $startedAt = microtime(true);
        $success   = false;
        $logData   = [
            'payment_id' => $payment->paymentId,
            'url'        => $url,
            'payload'    => $payload,
            'sent_at'    => now(),
        ];

        try {
            $response = Http::timeout(10)
                ->withHeader('X-Signature', $this->sign($payload))
                ->post($url, $payload);

            $durationMs = (int) round((microtime(true) - $startedAt) * 1000);
            $success    = $response->successful();

            OutboundWebhookLog::create(array_merge($logData, [
                'response_status' => $response->status(),
                'response_body'   => mb_substr((string) $response->body(), 0, 2000),
                'duration_ms'     => $durationMs,
                'success'         => $success,
            ]));

            $this->logger->info('Outbound notification sent', [
                'payment_id' => $payment->paymentId,
                'url'        => $url,
                'status'     => $response->status(),
                'duration_ms' => $durationMs,
            ]);

            $this->metrics->notificationSent($success);
        } catch (\Throwable $e) {
            $durationMs = (int) round((microtime(true) - $startedAt) * 1000);

            OutboundWebhookLog::create(array_merge($logData, [
                'duration_ms' => $durationMs,
                'success'     => false,
                'error'       => $e->getMessage(),
            ]));

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
