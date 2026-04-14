<?php

declare(strict_types=1);

namespace App\Payments\Application\Commands\RetryPayment;

use App\Payments\Application\DTOs\PaymentResultDTO;
use App\Payments\Application\PaymentProviderRegistry;
use App\Payments\Domain\Aggregates\Payment;
use App\Payments\Domain\Contracts\PaymentRepositoryInterface;
use App\Payments\Domain\Exceptions\PaymentException;
use App\Payments\Domain\ValueObjects\PaymentId;
use App\Payments\Infrastructure\Observability\MetricsService;
use App\Payments\Infrastructure\Observability\PaymentLogger;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

final readonly class RetryPaymentHandler
{
    public function __construct(
        private PaymentRepositoryInterface $repository,
        private PaymentProviderRegistry $registry,
        private PaymentLogger $logger,
        private MetricsService $metrics,
    ) {}

    public function handle(RetryPaymentCommand $command): PaymentResultDTO
    {
        $original = $this->repository->findById(PaymentId::fromString($command->paymentId));

        if ($original === null) {
            throw new PaymentException("Payment not found: {$command->paymentId}", Response::HTTP_NOT_FOUND);
        }

        if ($original->status()->value !== 'Cancelled') {
            throw new PaymentException(
                "Only Cancelled payments can be retried. Current status: {$original->status()->value}",
                Response::HTTP_CONFLICT,
            );
        }

        $provider = $this->registry->resolve($original->provider());

        return DB::transaction(function () use ($command, $original, $provider): PaymentResultDTO {
            $newPayment = Payment::create(
                id: PaymentId::generate(),
                amount: $original->amount(),
                description: $original->description(),
                provider: $original->provider(),
                idempotencyKey: $command->idempotencyKey,
                metadata: array_merge($original->metadata(), [
                    'retried_from' => $original->id()->toString(),
                ]),
            );

            $this->repository->save($newPayment);

            $providerResponse = $provider->createPayment(
                paymentId: $newPayment->id()->toString(),
                amount: $newPayment->amount(),
                description: $newPayment->description(),
                returnUrl: $command->returnUrl,
                idempotencyKey: $command->idempotencyKey,
            );

            $newPayment->assignExternalData(
                externalId: $providerResponse->externalId,
                confirmationUrl: $providerResponse->confirmationUrl,
                paymentMethodId: $providerResponse->paymentMethodId,
            );

            $this->repository->save($newPayment);

            $this->metrics->paymentCreated($provider->name());

            $this->logger->info('Payment retried', [
                'original_id' => $original->id()->toString(),
                'new_id'      => $newPayment->id()->toString(),
                'provider'    => $provider->name(),
            ]);

            return PaymentResultDTO::fromAggregate($newPayment);
        });
    }
}
