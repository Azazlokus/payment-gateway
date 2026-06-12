<?php

declare(strict_types=1);

namespace App\Contexts\Payments\Infrastructure\Jobs;

use App\Contexts\Payments\Domain\Contracts\PaymentRepositoryInterface;
use App\Contexts\Payments\Domain\Exceptions\InvalidPaymentStateException;
use App\Contexts\Payments\Domain\ValueObjects\ExternalId;
use App\Contexts\Payments\Infrastructure\Observability\PaymentLogger;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Throwable;

final class ProcessAlfaBankWebhookJob implements ShouldQueue
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
        $mdOrder = (string) ($this->payload['mdOrder'] ?? '');
        $operation = (string) ($this->payload['operation'] ?? '');

        $payment = $repository->findByExternalId($mdOrder);

        if ($payment === null) {
            $logger->warning('Альфа-Банк webhook job: платёж не найден', [
                'md_order' => $mdOrder,
                'operation' => $operation,
            ]);

            return;
        }

        try {
            match ($operation) {
                'deposited' => $payment->markAsSucceeded(ExternalId::fromString($mdOrder)),

                'refunded' => $payment->refund($payment->amount()),

                'reversed',
                'declinedByTimeout' => $payment->cancel('Cancelled by Alfa-Bank'),

                default => null,
            };

            $repository->save($payment);

            activity()
                ->withProperties(['md_order' => $mdOrder, 'operation' => $operation])
                ->log('alfabank.webhook.processed');

            $logger->info('Альфа-Банк webhook job: обработан', [
                'payment_id' => $payment->id()->toString(),
                'md_order' => $mdOrder,
                'operation' => $operation,
            ]);
        } catch (InvalidPaymentStateException) {
            $logger->info('Альфа-Банк webhook job: платёж уже в терминальном статусе (пропущено)', [
                'payment_id' => $payment->id()->toString(),
                'md_order' => $mdOrder,
            ]);
        }
    }

    public function failed(Throwable $exception): void
    {
        $mdOrder = $this->payload['mdOrder'] ?? 'unknown';
        $operation = $this->payload['operation'] ?? 'unknown';

        logger()->critical('Альфа-Банк webhook: обработка окончательно не удалась', [
            'md_order' => $mdOrder,
            'operation' => $operation,
            'error' => $exception->getMessage(),
            'payload' => $this->payload,
        ]);

        $slackUrl = config('services.slack.webhook_url');

        if ($slackUrl) {
            Http::post($slackUrl, [
                'text' => sprintf(
                    ':x: *Альфа-Банк webhook failed* after %d attempts'
                    ."\nOrder: `%s`\nOperation: `%s`\nError: `%s`",
                    $this->tries,
                    $mdOrder,
                    $operation,
                    $exception->getMessage(),
                ),
            ]);
        }
    }
}
