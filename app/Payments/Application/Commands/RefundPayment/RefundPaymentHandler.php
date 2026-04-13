<?php

declare(strict_types=1);

namespace App\Payments\Application\Commands\RefundPayment;

use App\Payments\Application\DTOs\PaymentResultDTO;
use App\Payments\Domain\Contracts\PaymentProviderInterface;
use App\Payments\Domain\Contracts\PaymentRepositoryInterface;
use App\Payments\Domain\Exceptions\PaymentException;
use App\Payments\Domain\ValueObjects\Money;
use App\Payments\Domain\ValueObjects\PaymentId;
use App\Payments\Infrastructure\Observability\PaymentLogger;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

final readonly class RefundPaymentHandler
{
    public function __construct(
        private PaymentRepositoryInterface $repository,
        private PaymentProviderInterface $provider,
        private PaymentLogger $logger,
    ) {}

    public function handle(RefundPaymentCommand $command): PaymentResultDTO
    {
        return DB::transaction(function () use ($command): PaymentResultDTO {
            $payment = $this->repository->findById(PaymentId::fromString($command->paymentId));

            if ($payment === null) {
                throw new PaymentException("Payment not found: {$command->paymentId}", Response::HTTP_NOT_FOUND);
            }

            if ($payment->externalId() === null) {
                throw new PaymentException('Payment has no external ID, cannot refund');
            }

            $refundAmount = $command->amountKopecks !== null
                ? Money::ofRub($command->amountKopecks)
                : $payment->amount();

            $this->provider->refundPayment($payment->externalId(), $refundAmount);

            $payment->refund($refundAmount);
            $this->repository->save($payment);

            activity()
                ->withProperties(['payment_id' => $command->paymentId, 'refund_amount' => $refundAmount->formatted()])
                ->log('payment.refunded');

            $this->logger->info('Payment refunded', [
                'payment_id' => $command->paymentId,
                'refund_amount' => $refundAmount->formatted(),
            ]);

            return PaymentResultDTO::fromAggregate($payment);
        });
    }
}
