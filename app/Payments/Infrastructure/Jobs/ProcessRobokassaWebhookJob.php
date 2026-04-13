<?php

declare(strict_types=1);

namespace App\Payments\Infrastructure\Jobs;

use App\Payments\Domain\Contracts\PaymentRepositoryInterface;
use App\Payments\Domain\Exceptions\InvalidPaymentStateException;
use App\Payments\Domain\ValueObjects\ExternalId;
use App\Payments\Domain\ValueObjects\PaymentId;
use App\Payments\Infrastructure\Observability\PaymentLogger;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Throwable;

final class ProcessRobokassaWebhookJob implements ShouldQueue
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
        PaymentRepositoryInterface $repository,
        PaymentLogger $logger,
    ): void {
        $internalId = (string) ($this->payload['Shp_paymentId'] ?? '');
        $invId      = (string) ($this->payload['InvId'] ?? '');

        $payment = $repository->findById(PaymentId::fromString($internalId));

        if ($payment === null) {
            $logger->warning('Robokassa webhook job: payment not found', [
                'shp_payment_id' => $internalId,
                'inv_id'         => $invId,
            ]);

            return;
        }

        try {
            // Update external_id to the real Robokassa InvId so refunds work correctly
            $realExternalId = ExternalId::fromString($invId);
            $payment->assignExternalData($realExternalId, '');

            $payment->markAsSucceeded($realExternalId);

            $repository->save($payment);

            activity()
                ->withProperties([
                    'shp_payment_id' => $internalId,
                    'inv_id'         => $invId,
                    'event'          => 'robokassa.payment.succeeded',
                ])
                ->log('webhook.processed');

            $logger->info('Robokassa webhook job processed', [
                'payment_id' => $payment->id()->toString(),
                'inv_id'     => $invId,
            ]);
        } catch (InvalidPaymentStateException $e) {
            // Idempotency: payment already in a terminal state — do not retry
            $logger->info('Robokassa webhook job: payment already in terminal status (skipped)', [
                'payment_id' => $payment->id()->toString(),
                'inv_id'     => $invId,
            ]);
        }
    }

    /**
     * Called after all $tries attempts are exhausted.
     * Logs the failure and sends a Slack alert when SLACK_WEBHOOK_URL is configured.
     */
    public function failed(Throwable $exception): void
    {
        $internalId = $this->payload['Shp_paymentId'] ?? 'unknown';
        $invId      = $this->payload['InvId'] ?? 'unknown';

        logger()->critical('Robokassa webhook processing permanently failed', [
            'shp_payment_id' => $internalId,
            'inv_id'         => $invId,
            'error'          => $exception->getMessage(),
            'payload'        => $this->payload,
        ]);

        $slackUrl = config('services.slack.webhook_url');

        if ($slackUrl) {
            Http::post($slackUrl, [
                'text' => sprintf(
                    ':x: *Robokassa webhook failed* after %d attempts'
                    . "\nInvId: `%s`\nInternal PaymentId: `%s`\nError: `%s`",
                    $this->tries,
                    $invId,
                    $internalId,
                    $exception->getMessage(),
                ),
            ]);
        }
    }
}
