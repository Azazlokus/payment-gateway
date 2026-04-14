<?php

declare(strict_types=1);

namespace App\Payments\Application\Commands\CreatePayment;

use App\Payments\Application\DTOs\PaymentResultDTO;
use App\Payments\Application\PaymentProviderRegistry;
use App\Payments\Domain\Aggregates\Payment;
use App\Payments\Domain\Contracts\PaymentRepositoryInterface;
use App\Payments\Domain\ValueObjects\Money;
use App\Payments\Domain\ValueObjects\PaymentId;
use App\Payments\Infrastructure\Observability\MetricsService;
use App\Payments\Infrastructure\Observability\PaymentLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class CreatePaymentHandler
{
    public function __construct(
        private PaymentRepositoryInterface $repository,
        private PaymentProviderRegistry $registry,
        private PaymentLogger $logger,
        private MetricsService $metrics,
    ) {}

    public function handle(CreatePaymentCommand $command): PaymentResultDTO
    {
        $provider      = $command->provider
            ? $this->registry->resolve($command->provider)
            : $this->registry->default();

        $correlationId = request()->header('X-Correlation-Id', (string) Str::uuid());

        $this->logger->info('Creating payment', [
            'correlation_id' => $correlationId,
            'amount'         => $command->amountKopecks,
            'idempotency_key' => $command->idempotencyKey,
            'provider'       => $provider->name(),
        ]);

        return DB::transaction(function () use ($command, $provider, $correlationId): PaymentResultDTO {
            $payment = Payment::create(
                id: PaymentId::generate(),
                amount: Money::ofRub($command->amountKopecks),
                description: $command->description,
                provider: $provider->name(),
                idempotencyKey: $command->idempotencyKey,
                metadata: array_merge($command->metadata, [
                    'user_id'        => $command->userId,
                    'correlation_id' => $correlationId,
                ]),
            );

            $this->repository->save($payment);

            $providerResponse = $provider->createPayment(
                paymentId: $payment->id()->toString(),
                amount: $payment->amount(),
                description: $payment->description(),
                returnUrl: $command->returnUrl,
                idempotencyKey: $command->idempotencyKey,
                options: $command->options,
            );

            $payment->assignExternalData(
                externalId: $providerResponse->externalId,
                confirmationUrl: $providerResponse->confirmationUrl,
                paymentMethodId: $providerResponse->paymentMethodId,
            );

            $this->repository->save($payment);

            activity()
                ->withProperties(['payment_id' => $payment->id()->toString(), 'amount' => $command->amountKopecks])
                ->log('payment.created');

            $this->metrics->paymentCreated($provider->name());
            $this->metrics->paymentAmount($provider->name(), $payment->amount()->currency()->value, $command->amountKopecks);

            $this->logger->info('Payment created successfully', [
                'correlation_id' => $correlationId,
                'payment_id'     => $payment->id()->toString(),
                'provider'       => $provider->name(),
                'external_id'    => $providerResponse->externalId->toString(),
            ]);

            return PaymentResultDTO::fromAggregate($payment);
        });
    }
}
