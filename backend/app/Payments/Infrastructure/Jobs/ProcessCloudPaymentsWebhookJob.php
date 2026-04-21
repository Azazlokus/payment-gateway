<?php

declare(strict_types=1);

namespace App\Payments\Infrastructure\Jobs;

use App\Payments\Domain\Contracts\PaymentRepositoryInterface;
use App\Payments\Domain\Exceptions\InvalidPaymentStateException;
use App\Payments\Domain\ValueObjects\ExternalId;
use App\Payments\Domain\ValueObjects\Money;
use App\Payments\Infrastructure\Observability\MetricsService;
use App\Payments\Infrastructure\Observability\PaymentLogger;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Throwable;

final class ProcessCloudPaymentsWebhookJob implements ShouldQueue
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
        MetricsService $metrics,
    ): void {
        $transactionId = (string) ($this->payload['TransactionId'] ?? '');
        $status        = (string) ($this->payload['Status'] ?? '');

        // CloudPayments передаёт InvoiceId = наш внутренний paymentId
        $invoiceId = (string) ($this->payload['InvoiceId'] ?? '');

        $payment = $repository->findByExternalId($transactionId);

        // Если транзакция ещё не привязана — ищем по InvoiceId (внутренний ID)
        if ($payment === null && $invoiceId !== '') {
            $payment = $repository->findByExternalId($invoiceId);
        }

        if ($payment === null) {
            $logger->warning('CloudPayments webhook job: платёж не найден', [
                'transaction_id' => $transactionId,
                'invoice_id'     => $invoiceId,
                'status'         => $status,
            ]);

            return;
        }

        try {
            $mappedStatus = match ($status) {
                'Completed', 'Authorized' => 'succeeded',
                'Cancelled', 'Declined'   => 'canceled',
                'Refunded'                => 'refunded',
                default                   => null,
            };

            if ($mappedStatus === null) {
                return;
            }

            $externalId = ExternalId::fromString($transactionId ?: $invoiceId);

            match ($mappedStatus) {
                'succeeded' => $payment->markAsSucceeded($externalId),
                'canceled'  => $payment->cancel('Cancelled by CloudPayments'),
                'refunded'  => $this->handleRefund($payment, $logger),
            };

            $repository->save($payment);

            $metrics->webhookProcessed('cloudpayments', $mappedStatus);

            activity()
                ->withProperties([
                    'transaction_id' => $transactionId,
                    'status'         => $status,
                ])
                ->log('cloudpayments.webhook.processed');

            $logger->info('CloudPayments webhook job: обработан', [
                'payment_id'     => $payment->id()->toString(),
                'transaction_id' => $transactionId,
                'status'         => $status,
            ]);
        } catch (InvalidPaymentStateException) {
            $logger->info('CloudPayments webhook job: платёж уже в терминальном статусе (пропущено)', [
                'payment_id'     => $payment->id()->toString(),
                'transaction_id' => $transactionId,
            ]);
        }
    }

    private function handleRefund(
        \App\Payments\Domain\Aggregates\Payment $payment,
        PaymentLogger $logger,
    ): void {
        $kopecks = isset($this->payload['Amount'])
            ? (int) round((float) $this->payload['Amount'] * 100)
            : null;

        $refundAmount = $kopecks !== null
            ? Money::ofRub($kopecks)
            : $payment->amount();

        $payment->refund($refundAmount);

        $logger->info('CloudPayments webhook: возврат', [
            'payment_id' => $payment->id()->toString(),
            'amount'     => $refundAmount->amount(),
        ]);
    }

    public function failed(Throwable $exception): void
    {
        $transactionId = $this->payload['TransactionId'] ?? 'unknown';
        $status        = $this->payload['Status'] ?? 'unknown';

        logger()->critical('CloudPayments webhook: обработка окончательно не удалась', [
            'transaction_id' => $transactionId,
            'status'         => $status,
            'error'          => $exception->getMessage(),
            'payload'        => $this->payload,
        ]);

        $slackUrl = config('services.slack.webhook_url');

        if ($slackUrl) {
            Http::post($slackUrl, [
                'text' => sprintf(
                    ':x: *CloudPayments webhook failed* after %d attempts'
                    ."\nTransactionId: `%s`\nStatus: `%s`\nError: `%s`",
                    $this->tries,
                    $transactionId,
                    $status,
                    $exception->getMessage(),
                ),
            ]);
        }
    }
}
