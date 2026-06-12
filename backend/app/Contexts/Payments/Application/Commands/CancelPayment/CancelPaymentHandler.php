<?php

declare(strict_types=1);

namespace App\Contexts\Payments\Application\Commands\CancelPayment;

use App\Contexts\Payments\Application\DTOs\PaymentResultDTO;
use App\Contexts\Payments\Domain\Aggregates\Payment;
use App\Contexts\Payments\Domain\Contracts\PaymentRepositoryInterface;
use App\Contexts\Payments\Domain\Exceptions\PaymentException;
use App\Contexts\Payments\Domain\ValueObjects\PaymentId;
use App\Contexts\Payments\Infrastructure\Observability\PaymentLogger;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

final readonly class CancelPaymentHandler
{
    public function __construct(
        private PaymentRepositoryInterface $repository,
        private PaymentLogger $logger,
    ) {}

    public function handle(CancelPaymentCommand $command): PaymentResultDTO
    {
        return DB::transaction(function () use ($command): PaymentResultDTO {
            $payment = $this->repository->findById(PaymentId::fromString($command->paymentId));

            if (! $payment instanceof Payment) {
                throw new PaymentException("Payment not found: {$command->paymentId}", Response::HTTP_NOT_FOUND);
            }

            $payment->cancel($command->reason);
            $this->repository->save($payment);

            activity()
                ->withProperties(['payment_id' => $command->paymentId, 'reason' => $command->reason])
                ->log('payment.cancelled');

            $this->logger->info('Payment cancelled', [
                'payment_id' => $command->paymentId,
                'reason' => $command->reason,
            ]);

            return PaymentResultDTO::fromAggregate($payment);
        });
    }
}
