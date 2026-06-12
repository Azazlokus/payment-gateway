<?php

declare(strict_types=1);

namespace App\Contexts\Payments\Application\Commands\RefundPayment;

use App\Contexts\Payments\Application\DTOs\PaymentResultDTO;
use App\Contexts\Payments\Domain\Aggregates\Payment;
use App\Contexts\Payments\Domain\Contracts\PaymentRepositoryInterface;
use App\Contexts\Payments\Domain\Enums\RefundStatus;
use App\Contexts\Payments\Domain\Exceptions\PaymentException;
use App\Contexts\Payments\Domain\ValueObjects\ExternalId;
use App\Contexts\Payments\Domain\ValueObjects\Money;
use App\Contexts\Payments\Domain\ValueObjects\PaymentId;
use App\Contexts\Payments\Infrastructure\Jobs\ProcessRefundJob;
use App\Contexts\Payments\Infrastructure\Observability\PaymentLogger;
use App\Contexts\Payments\Infrastructure\Persistence\Models\Refund;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final readonly class RefundPaymentHandler
{
    public function __construct(
        private PaymentRepositoryInterface $repository,
        private PaymentLogger $logger,
    ) {}

    public function handle(RefundPaymentCommand $command): PaymentResultDTO
    {
        // Idempotency check
        if ($command->idempotencyKey !== null) {
            $cacheKey = "refund_idem:{$command->idempotencyKey}";
            $cached = Cache::get($cacheKey);

            if ($cached !== null) {
                $payment = $this->repository->findById(PaymentId::fromString($cached));
                if ($payment instanceof Payment) {
                    $this->logger->info('Refund idempotency hit', [
                        'idempotency_key' => $command->idempotencyKey,
                        'payment_id' => $cached,
                    ]);

                    return PaymentResultDTO::fromAggregate($payment);
                }
            }
        }

        $result = DB::transaction(function () use ($command): PaymentResultDTO {
            $payment = $this->repository->findById(PaymentId::fromString($command->paymentId));

            if (! $payment instanceof Payment) {
                throw new PaymentException("Payment not found: {$command->paymentId}", Response::HTTP_NOT_FOUND);
            }

            if (! $payment->externalId() instanceof ExternalId) {
                throw new PaymentException('Payment has no external ID, cannot refund', Response::HTTP_CONFLICT);
            }

            $refundAmount = $command->amountKopecks !== null
                ? Money::ofRub($command->amountKopecks)
                : $payment->amount();

            // Validate refund is possible (checks status + available amount)
            $payment->requestRefund($refundAmount, $command->reason);
            $this->repository->save($payment);

            // Create refund record as pending — saga starts here
            $refund = Refund::create([
                'payment_id' => $command->paymentId,
                'amount' => $refundAmount->amount(),
                'currency' => $payment->amount()->currency()->value,
                'reason' => $command->reason ?: null,
                'status' => RefundStatus::Pending,
                'idempotency_key' => $command->idempotencyKey ?? (string) Str::uuid(),
            ]);

            // Dispatch async job to call provider
            ProcessRefundJob::dispatch($refund->id);

            $this->logger->info('Refund saga started', [
                'payment_id' => $command->paymentId,
                'refund_id' => $refund->id,
                'refund_amount' => $refundAmount->formatted(),
                'provider' => $payment->provider(),
            ]);

            return PaymentResultDTO::fromAggregate($payment);
        });

        if ($command->idempotencyKey !== null) {
            Cache::put("refund_idem:{$command->idempotencyKey}", $command->paymentId, now()->addDay());
        }

        return $result;
    }
}
