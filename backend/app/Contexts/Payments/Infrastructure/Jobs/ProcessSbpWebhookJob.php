<?php

declare(strict_types=1);

namespace App\Contexts\Payments\Infrastructure\Jobs;

use App\Contexts\Payments\Domain\Aggregates\Payment;
use App\Contexts\Payments\Domain\Contracts\PaymentRepositoryInterface;
use App\Contexts\Payments\Domain\Exceptions\InvalidPaymentStateException;
use App\Contexts\Payments\Domain\ValueObjects\ExternalId;
use App\Contexts\Payments\Infrastructure\Observability\PaymentLogger;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Throwable;

final class ProcessSbpWebhookJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /** @var array<int, int> */
    public array $backoff = [10, 30, 60, 120, 300];

    /** @param array<string, mixed> $payload */
    public function __construct(
        private readonly array $payload,
    ) {}

    public function handle(
        PaymentRepositoryInterface $repository,
        PaymentLogger $logger,
    ): void {
        $qrId = (string) ($this->payload['qrId'] ?? '');
        $status = (string) ($this->payload['status'] ?? '');

        $payment = $repository->findByExternalId($qrId);

        if (! $payment instanceof Payment) {
            $logger->warning('СБП webhook job: платёж не найден', ['qr_id' => $qrId]);

            return;
        }

        try {
            match ($status) {
                'PAID' => $payment->markAsSucceeded(ExternalId::fromString($qrId)),
                'CANCELLED', 'EXPIRED' => $payment->cancel('Cancelled by СБП'),
                default => null,
            };

            $repository->save($payment);

            activity()
                ->withProperties(['qr_id' => $qrId, 'status' => $status])
                ->log('sbp.webhook.processed');

            $logger->info('СБП webhook job: обработан', [
                'payment_id' => $payment->id()->toString(),
                'qr_id' => $qrId,
                'status' => $status,
            ]);
        } catch (InvalidPaymentStateException) {
            $logger->info('СБП webhook job: платёж уже в терминальном статусе (пропущено)', [
                'payment_id' => $payment->id()->toString(),
                'qr_id' => $qrId,
            ]);
        }
    }

    public function failed(Throwable $exception): void
    {
        $qrId = $this->payload['qrId'] ?? 'unknown';

        logger()->critical('СБП webhook: обработка окончательно не удалась', [
            'qr_id' => $qrId,
            'error' => $exception->getMessage(),
            'payload' => $this->payload,
        ]);

        $slackUrl = config('services.slack.webhook_url');

        if ($slackUrl) {
            Http::post($slackUrl, [
                'text' => sprintf(
                    ':x: *СБП webhook failed* after %d attempts'
                    ."\nQR ID: `%s`\nError: `%s`",
                    $this->tries,
                    $qrId,
                    $exception->getMessage(),
                ),
            ]);
        }
    }
}
