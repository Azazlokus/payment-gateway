<?php

declare(strict_types=1);

namespace App\Payments\Application\Commands\RefundPayment;

use App\Payments\Application\DTOs\PaymentResultDTO;
use App\Payments\Application\PaymentProviderRegistry;
use App\Payments\Domain\Contracts\PaymentRepositoryInterface;
use App\Payments\Domain\Exceptions\PaymentException;
use App\Payments\Domain\ValueObjects\Money;
use App\Payments\Domain\ValueObjects\PaymentId;
use App\Payments\Infrastructure\Observability\MetricsService;
use App\Payments\Infrastructure\Observability\NotificationService;
use App\Payments\Infrastructure\Observability\PaymentLogger;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

final readonly class RefundPaymentHandler
{
    public function __construct(
        private PaymentRepositoryInterface $repository,
        private PaymentProviderRegistry $registry,
        private PaymentLogger $logger,
        private MetricsService $metrics,
        private NotificationService $notifications,
    ) {}

    public function handle(RefundPaymentCommand $command): PaymentResultDTO
    {
        if ($command->idempotencyKey !== null) {
            $cacheKey = "refund_idem:{$command->idempotencyKey}";
            $cached   = Cache::get($cacheKey);

            if ($cached !== null) {
                $payment = $this->repository->findById(PaymentId::fromString($cached));
                if ($payment !== null) {
                    $this->logger->info('Refund idempotency hit', [
                        'idempotency_key' => $command->idempotencyKey,
                        'payment_id'      => $cached,
                    ]);

                    return PaymentResultDTO::fromAggregate($payment);
                }
            }
        }

        $result = DB::transaction(function () use ($command): PaymentResultDTO {
            $payment = $this->repository->findById(PaymentId::fromString($command->paymentId));

            if ($payment === null) {
                throw new PaymentException("Payment not found: {$command->paymentId}", Response::HTTP_NOT_FOUND);
            }

            if ($payment->externalId() === null) {
                throw new PaymentException('Payment has no external ID, cannot refund', Response::HTTP_CONFLICT);
            }

            $provider     = $this->registry->resolve($payment->provider());
            $refundAmount = $command->amountKopecks !== null
                ? Money::ofRub($command->amountKopecks)
                : $payment->amount();

            $provider->refundPayment($payment->externalId(), $refundAmount);

            $payment->refund($refundAmount);
            $this->repository->save($payment);

            activity()
                ->withProperties(['payment_id' => $command->paymentId, 'refund_amount' => $refundAmount->formatted()])
                ->log('payment.refunded');

            $this->metrics->paymentRefunded($payment->provider(), $refundAmount->amount());

            $this->logger->info('Payment refunded', [
                'payment_id'    => $command->paymentId,
                'refund_amount' => $refundAmount->formatted(),
                'provider'      => $payment->provider(),
            ]);

            return PaymentResultDTO::fromAggregate($payment);
        });

        if ($command->idempotencyKey !== null) {
            Cache::put("refund_idem:{$command->idempotencyKey}", $command->paymentId, now()->addDay());
        }

        // Уведомление клиента — вне транзакции, сбой не откатывает рефанд
        $payment = $this->repository->findById(PaymentId::fromString($command->paymentId));
        if ($payment !== null) {
            $this->notifications->notify($result, $payment->metadata());
        }

        return $result;
    }
}
