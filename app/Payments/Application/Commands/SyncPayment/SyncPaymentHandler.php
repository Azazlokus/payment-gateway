<?php

declare(strict_types=1);

namespace App\Payments\Application\Commands\SyncPayment;

use App\Payments\Application\DTOs\PaymentResultDTO;
use App\Payments\Domain\Contracts\PaymentProviderInterface;
use App\Payments\Domain\Contracts\PaymentRepositoryInterface;
use App\Payments\Domain\Exceptions\InvalidPaymentStateException;
use App\Payments\Domain\Exceptions\PaymentException;
use App\Payments\Domain\ValueObjects\PaymentId;
use App\Payments\Infrastructure\Observability\PaymentLogger;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

final readonly class SyncPaymentHandler
{
    public function __construct(
        private PaymentRepositoryInterface $repository,
        private PaymentProviderInterface   $provider,
        private PaymentLogger              $logger,
    ) {
    }

    public function handle(SyncPaymentCommand $command): PaymentResultDTO
    {
        return DB::transaction(function () use ($command): PaymentResultDTO {
            $payment = $this->repository->findById(PaymentId::fromString($command->paymentId));

            if ($payment === null) {
                throw new PaymentException("Payment not found: {$command->paymentId}", Response::HTTP_NOT_FOUND);
            }

            if ($payment->externalId() === null || $payment->status()->isTerminal()) {
                return PaymentResultDTO::fromAggregate($payment);
            }

            $providerResponse = $this->provider->getPayment($payment->externalId());

            $this->logger->info('Syncing payment status', [
                'payment_id'      => $command->paymentId,
                'provider_status' => $providerResponse->status,
            ]);

            try {
                match ($providerResponse->status) {
                    'succeeded' => $payment->markAsSucceeded($payment->externalId()),
                    'canceled'  => $payment->cancel('Cancelled by provider'),
                    default     => null,
                };
            } catch (InvalidPaymentStateException) {
                // параллельный запрос уже обновил
            }

            $this->repository->save($payment);

            return PaymentResultDTO::fromAggregate($payment);
        });
    }
}
