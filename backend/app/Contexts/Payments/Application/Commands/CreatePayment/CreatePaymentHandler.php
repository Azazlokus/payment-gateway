<?php

declare(strict_types=1);

namespace App\Contexts\Payments\Application\Commands\CreatePayment;

use App\Contexts\Payments\Application\DTOs\PaymentResultDTO;
use App\Contexts\Payments\Application\DTOs\SplitRuleDTO;
use App\Contexts\Payments\Application\PaymentProviderRegistry;
use App\Contexts\Payments\Domain\Aggregates\Payment;
use App\Contexts\Payments\Domain\Contracts\PaymentRepositoryInterface;
use App\Contexts\Payments\Domain\Contracts\SupportsSplitPayments;
use App\Contexts\Payments\Domain\Contracts\SupportsTwoPhasePayments;
use App\Contexts\Payments\Domain\Exceptions\PaymentException;
use App\Contexts\Payments\Domain\ValueObjects\Money;
use App\Contexts\Payments\Domain\ValueObjects\PaymentId;
use App\Contexts\Payments\Domain\ValueObjects\SplitRule;
use App\Contexts\Payments\Infrastructure\Antifraud\VelocityChecker;
use App\Contexts\Payments\Infrastructure\Observability\MetricsService;
use App\Contexts\Payments\Infrastructure\Observability\PaymentLogger;
use App\Contexts\Payments\Infrastructure\Tenant\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class CreatePaymentHandler
{
    public function __construct(
        private PaymentRepositoryInterface $repository,
        private PaymentProviderRegistry $registry,
        private PaymentLogger $logger,
        private MetricsService $metrics,
        private VelocityChecker $velocityChecker,
        private TenantContext $tenantContext,
    ) {}

    public function handle(CreatePaymentCommand $command): PaymentResultDTO
    {
        $provider = $command->provider
            ? $this->registry->resolve($command->provider)
            : $this->registry->default();

        $correlationId = request()->header('X-Correlation-Id', (string) Str::uuid());

        $this->logger->info('Creating payment', [
            'correlation_id' => $correlationId,
            'amount' => $command->amountKopecks,
            'idempotency_key' => $command->idempotencyKey,
            'provider' => $provider->name(),
        ]);

        // Antifraud velocity checks — before transaction, before provider call
        $dimensions = [
            'ip' => request()->ip(),
            'user_id' => $command->userId !== null ? (string) $command->userId : null,
            'payment_method_id' => $command->options->paymentMethodId,
        ];
        $this->velocityChecker->check($dimensions, $command->amountKopecks);

        return DB::transaction(function () use ($command, $provider, $correlationId, $dimensions): PaymentResultDTO {
            $splitRules = array_map(
                fn (SplitRuleDTO $dto): SplitRule => new SplitRule(
                    accountId: $dto->accountId,
                    amount: Money::ofRub($dto->amountKopecks),
                    description: $dto->description,
                ),
                $command->splits,
            );

            $payment = Payment::create(
                id: PaymentId::generate(),
                amount: Money::ofRub($command->amountKopecks),
                description: $command->description,
                provider: $provider->name(),
                idempotencyKey: $command->idempotencyKey,
                metadata: array_merge($command->metadata, [
                    'user_id' => $command->userId,
                    'correlation_id' => $correlationId,
                ]),
                splits: $splitRules,
                tenantId: $this->tenantContext->has() ? $this->tenantContext->get() : null,
            );

            $this->repository->save($payment);

            if ($payment->hasSplits()) {
                if (! $provider instanceof SupportsSplitPayments) {
                    throw new PaymentException(
                        "Provider {$provider->name()} does not support split payments",
                    );
                }

                $providerResponse = $provider->createSplitPayment(
                    paymentId: $payment->id()->toString(),
                    amount: $payment->amount(),
                    description: $payment->description(),
                    returnUrl: $command->returnUrl,
                    idempotencyKey: $command->idempotencyKey,
                    splits: $splitRules,
                    options: $command->options,
                );
            } elseif ($command->manualCapture) {
                if (! $provider instanceof SupportsTwoPhasePayments) {
                    throw new PaymentException(
                        "Provider {$provider->name()} does not support manual capture",
                    );
                }

                $providerResponse = $provider->authorizePayment(
                    paymentId: $payment->id()->toString(),
                    amount: $payment->amount(),
                    description: $payment->description(),
                    returnUrl: $command->returnUrl,
                    idempotencyKey: $command->idempotencyKey,
                    options: $command->options,
                );
            } else {
                $providerResponse = $provider->createPayment(
                    paymentId: $payment->id()->toString(),
                    amount: $payment->amount(),
                    description: $payment->description(),
                    returnUrl: $command->returnUrl,
                    idempotencyKey: $command->idempotencyKey,
                    options: $command->options,
                );
            }

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

            // Record velocity event after successful creation
            $this->velocityChecker->record($dimensions, $command->amountKopecks, $payment->id()->toString());

            $this->logger->info('Payment created successfully', [
                'correlation_id' => $correlationId,
                'payment_id' => $payment->id()->toString(),
                'provider' => $provider->name(),
                'external_id' => $providerResponse->externalId->toString(),
            ]);

            return PaymentResultDTO::fromAggregate($payment);
        });
    }
}
