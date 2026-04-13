<?php

declare(strict_types=1);

namespace App\Payments\Application\Commands\CreatePayment;

use App\Payments\Application\DTOs\PaymentResultDTO;
use App\Payments\Domain\Aggregates\Payment;
use App\Payments\Domain\Contracts\PaymentProviderInterface;
use App\Payments\Domain\Contracts\PaymentRepositoryInterface;
use App\Payments\Domain\ValueObjects\Money;
use App\Payments\Domain\ValueObjects\PaymentId;
use App\Payments\Infrastructure\Observability\PaymentLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class CreatePaymentHandler
{
    public function __construct(
        private PaymentRepositoryInterface $repository,
        private PaymentProviderInterface $provider,
        private PaymentLogger $logger,
    ) {}

    public function handle(CreatePaymentCommand $command): PaymentResultDTO
    {
        $correlationId = request()->header('X-Correlation-Id', (string) Str::uuid());

        $this->logger->info('Creating payment', [
            'correlation_id' => $correlationId,
            'amount' => $command->amountKopecks,
            'idempotency_key' => $command->idempotencyKey,
        ]);

        return DB::transaction(function () use ($command, $correlationId): PaymentResultDTO {
            $payment = Payment::create(
                id: PaymentId::generate(),
                amount: Money::ofRub($command->amountKopecks),
                description: $command->description,
                provider: $this->provider->name(),
                idempotencyKey: $command->idempotencyKey,
                metadata: array_merge($command->metadata, [
                    'user_id' => $command->userId,
                    'correlation_id' => $correlationId,
                ]),
            );

            $this->repository->save($payment);

            $providerResponse = $this->provider->createPayment(
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

            $this->logger->info('Payment created successfully', [
                'correlation_id' => $correlationId,
                'payment_id' => $payment->id()->toString(),
                'external_id' => $providerResponse->externalId->toString(),
            ]);

            return PaymentResultDTO::fromAggregate($payment);
        });
    }
}
