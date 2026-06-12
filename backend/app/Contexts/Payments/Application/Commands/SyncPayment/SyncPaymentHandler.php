<?php

declare(strict_types=1);

namespace App\Contexts\Payments\Application\Commands\SyncPayment;

use App\Contexts\Payments\Application\DTOs\PaymentResultDTO;
use App\Contexts\Payments\Application\PaymentProviderRegistry;
use App\Contexts\Payments\Domain\Aggregates\Payment;
use App\Contexts\Payments\Domain\Contracts\PaymentRepositoryInterface;
use App\Contexts\Payments\Domain\Exceptions\InvalidPaymentStateException;
use App\Contexts\Payments\Domain\Exceptions\PaymentException;
use App\Contexts\Payments\Domain\ValueObjects\ExternalId;
use App\Contexts\Payments\Domain\ValueObjects\PaymentId;
use App\Contexts\Payments\Infrastructure\Observability\PaymentLogger;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

final readonly class SyncPaymentHandler
{
    public function __construct(
        private PaymentRepositoryInterface $repository,
        private PaymentProviderRegistry $registry,
        private PaymentLogger $logger,
    ) {}

    public function handle(SyncPaymentCommand $command): PaymentResultDTO
    {
        return DB::transaction(function () use ($command): PaymentResultDTO {
            $payment = $this->repository->findById(PaymentId::fromString($command->paymentId));

            if (! $payment instanceof Payment) {
                throw new PaymentException("Payment not found: {$command->paymentId}", Response::HTTP_NOT_FOUND);
            }

            if (! $payment->externalId() instanceof ExternalId || $payment->status()->isTerminal()) {
                return PaymentResultDTO::fromAggregate($payment);
            }

            $provider = $this->registry->resolve($payment->provider());
            $providerResponse = $provider->getPayment($payment->externalId());

            $this->logger->info('Syncing payment status', [
                'payment_id' => $command->paymentId,
                'provider_status' => $providerResponse->status,
            ]);

            try {
                match ($providerResponse->status) {
                    'waiting_for_capture' => $payment->authorize($payment->externalId()),
                    'succeeded' => $payment->markAsSucceeded($payment->externalId()),
                    'canceled' => $payment->cancel('Cancelled by provider'),
                    default => null,
                };
            } catch (InvalidPaymentStateException) {
                // параллельный запрос уже обновил
            }

            $this->repository->save($payment);

            return PaymentResultDTO::fromAggregate($payment);
        });
    }
}
