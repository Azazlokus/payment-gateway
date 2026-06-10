<?php

declare(strict_types=1);

namespace App\Payments\Infrastructure\Jobs;

use App\Payments\Application\DTOs\PaymentResultDTO;
use App\Payments\Application\PaymentProviderRegistry;
use App\Payments\Domain\Contracts\PaymentRepositoryInterface;
use App\Payments\Domain\Enums\RefundStatus;
use App\Payments\Domain\ValueObjects\Money;
use App\Payments\Domain\ValueObjects\PaymentId;
use App\Payments\Infrastructure\Observability\MetricsService;
use App\Payments\Infrastructure\Observability\NotificationService;
use App\Payments\Infrastructure\Observability\PaymentLogger;
use App\Payments\Infrastructure\Persistence\Models\Refund;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class ProcessRefundJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    private const MAX_ATTEMPTS = 5;

    /** Backoff: 10s, 30s, 60s, 120s, 300s */
    private const BACKOFF_SECONDS = [10, 30, 60, 120, 300];

    public function __construct(
        public readonly string $refundId,
    ) {
        $this->onQueue('refunds');
    }

    public function handle(
        PaymentRepositoryInterface $repository,
        PaymentProviderRegistry $registry,
        PaymentLogger $logger,
        MetricsService $metrics,
        NotificationService $notifications,
    ): void {
        $refund = Refund::find($this->refundId);

        if ($refund === null) {
            $logger->error('ProcessRefundJob: refund not found', ['refund_id' => $this->refundId]);

            return;
        }

        if ($refund->status === RefundStatus::Succeeded) {
            return;
        }

        if ($refund->attempts >= self::MAX_ATTEMPTS) {
            $refund->update(['status' => RefundStatus::RequiresReview]);
            $logger->error('ProcessRefundJob: max attempts reached, requires review', [
                'refund_id' => $this->refundId,
                'payment_id' => $refund->payment_id,
                'attempts' => $refund->attempts,
            ]);
            $metrics->increment('refunds_requires_review_total', ['provider' => 'unknown']);

            return;
        }

        $payment = $repository->findById(PaymentId::fromString($refund->payment_id));

        if ($payment === null || $payment->externalId() === null) {
            $refund->update([
                'status' => RefundStatus::Failed,
                'last_error' => 'Payment not found or has no external ID',
            ]);

            return;
        }

        $provider = $registry->resolve($payment->provider());
        $refundAmount = Money::ofRub((int) $refund->amount);

        // Mark as processing
        $refund->update([
            'status' => RefundStatus::Processing,
            'attempts' => $refund->attempts + 1,
        ]);

        try {
            $providerResponse = $provider->refundPayment($payment->externalId(), $refundAmount);

            // Provider succeeded → update aggregate + refund record
            $payment->refund($refundAmount);
            $repository->save($payment);

            $refund->update([
                'status' => RefundStatus::Succeeded,
                'external_id' => $providerResponse->externalId->toString(),
                'last_error' => null,
            ]);

            $metrics->paymentRefunded($payment->provider(), $refundAmount->amount());
            $logger->info('ProcessRefundJob: refund succeeded', [
                'refund_id' => $this->refundId,
                'payment_id' => $refund->payment_id,
                'amount' => $refundAmount->formatted(),
                'attempt' => $refund->attempts,
            ]);

            // Notify client
            $notifications->notify(
                PaymentResultDTO::fromAggregate($payment),
                $payment->metadata(),
            );
        } catch (\Throwable $e) {
            $attempt = $refund->attempts;
            $isAmbiguous = $this->isAmbiguousError($e);

            if ($isAmbiguous) {
                // Timeout / network error — unclear if provider processed refund
                $refund->update([
                    'status' => RefundStatus::RequiresReview,
                    'last_error' => "Ambiguous: {$e->getMessage()}",
                ]);

                $logger->error('ProcessRefundJob: ambiguous failure, requires review', [
                    'refund_id' => $this->refundId,
                    'payment_id' => $refund->payment_id,
                    'error' => $e->getMessage(),
                ]);
                $metrics->increment('refunds_requires_review_total', ['provider' => $payment->provider()]);

                return;
            }

            $nextRetryAt = now()->addSeconds(self::BACKOFF_SECONDS[$attempt - 1] ?? 300);

            $refund->update([
                'status' => RefundStatus::Failed,
                'last_error' => $e->getMessage(),
                'next_retry_at' => $nextRetryAt,
            ]);

            $logger->warning('ProcessRefundJob: refund failed, will retry', [
                'refund_id' => $this->refundId,
                'payment_id' => $refund->payment_id,
                'attempt' => $attempt,
                'error' => $e->getMessage(),
                'next_retry_at' => $nextRetryAt->toIso8601String(),
            ]);

            self::dispatch($this->refundId)->delay($nextRetryAt);
        }
    }

    private function isAmbiguousError(\Throwable $e): bool
    {
        $message = $e->getMessage();

        return str_contains($message, 'timed out')
            || str_contains($message, 'cURL error')
            || str_contains($message, 'Connection reset');
    }
}
