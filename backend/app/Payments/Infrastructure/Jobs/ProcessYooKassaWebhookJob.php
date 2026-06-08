<?php

declare(strict_types=1);

namespace App\Payments\Infrastructure\Jobs;

use App\Payments\Domain\Contracts\PaymentProviderInterface;
use App\Payments\Domain\Contracts\PaymentRepositoryInterface;
use App\Payments\Domain\Exceptions\InvalidPaymentStateException;
use App\Payments\Domain\ValueObjects\Money;
use App\Payments\Infrastructure\Observability\PaymentLogger;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Throwable;

final class ProcessYooKassaWebhookJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /** @var array<int, int> Экспоненциальный backoff: 10s → 30s → 60s → 120s → 300s */
    public array $backoff = [10, 30, 60, 120, 300];

    /** @param array<string, mixed> $payload */
    public function __construct(
        private readonly array $payload,
    ) {}

    public function handle(
        PaymentProviderInterface $provider,
        PaymentRepositoryInterface $repository,
        PaymentLogger $logger,
    ): void {
        $providerResponse = $provider->parseWebhook($this->payload);
        $event = $this->payload['event'] ?? '';

        $payment = $repository->findByExternalId($providerResponse->externalId->toString());

        if ($payment === null) {
            $logger->warning('Webhook job: payment not found', [
                'external_id' => $providerResponse->externalId->toString(),
                'event' => $event,
            ]);

            return;
        }

        try {
            match ($event) {
                'payment.waiting_for_capture' => $payment->authorize($providerResponse->externalId),

                'payment.succeeded' => $payment->markAsSucceeded($providerResponse->externalId),

                'payment.canceled' => $payment->cancel('Cancelled by YooKassa'),

                'refund.succeeded' => $payment->refund(
                    $providerResponse->refundAmountKopecks !== null
                        ? Money::ofRub($providerResponse->refundAmountKopecks)
                        : $payment->amount()
                ),

                default => null,
            };

            $repository->save($payment);

            activity()
                ->withProperties(['external_id' => $providerResponse->externalId->toString(), 'event' => $event])
                ->log('webhook.processed');

            $logger->info('Webhook job processed', [
                'event' => $event,
                'payment_id' => $payment->id()->toString(),
            ]);
        } catch (InvalidPaymentStateException $e) {
            // Идемпотентность: платёж уже в нужном статусе — не ретраим
            $logger->info('Webhook job: payment already in terminal status (skipped)', [
                'event' => $event,
                'payment_id' => $payment->id()->toString(),
            ]);
        }
    }

    /**
     * Вызывается после того как исчерпаны все $tries попыток.
     * Логируем ошибку и отправляем алерт в Slack, если настроен SLACK_WEBHOOK_URL.
     */
    public function failed(Throwable $exception): void
    {
        $event = $this->payload['event'] ?? 'unknown';
        $externalId = $this->payload['object']['id'] ?? 'unknown';

        logger()->critical('Webhook processing permanently failed', [
            'event' => $event,
            'external_id' => $externalId,
            'error' => $exception->getMessage(),
            'payload' => $this->payload,
        ]);

        $slackUrl = config('services.slack.webhook_url');

        if ($slackUrl) {
            Http::post($slackUrl, [
                'text' => sprintf(
                    ':x: *Webhook failed* after %d attempts'
                    ."\nEvent: `%s`\nExternal ID: `%s`\nError: `%s`",
                    $this->tries,
                    $event,
                    $externalId,
                    $exception->getMessage(),
                ),
            ]);
        }
    }
}
