<?php

declare(strict_types=1);

namespace App\Contexts\Payments\Application\Commands\ChargeToken;

use App\Contexts\Payments\Application\DTOs\PaymentResultDTO;
use App\Contexts\Payments\Application\PaymentProviderRegistry;
use App\Contexts\Payments\Domain\Aggregates\Payment;
use App\Contexts\Payments\Domain\Aggregates\PaymentMethod;
use App\Contexts\Payments\Domain\Contracts\PaymentMethodRepositoryInterface;
use App\Contexts\Payments\Domain\Contracts\PaymentRepositoryInterface;
use App\Contexts\Payments\Domain\Contracts\SupportsTokenization;
use App\Contexts\Payments\Domain\Exceptions\PaymentException;
use App\Contexts\Payments\Domain\ValueObjects\Money;
use App\Contexts\Payments\Domain\ValueObjects\PaymentId;
use App\Contexts\Payments\Domain\ValueObjects\PaymentMethodId;
use App\Contexts\Payments\Infrastructure\Observability\MetricsService;
use App\Contexts\Payments\Infrastructure\Observability\PaymentLogger;
use App\Contexts\Payments\Infrastructure\Tenant\TenantContext;
use Illuminate\Support\Facades\DB;

final readonly class ChargeTokenHandler
{
    public function __construct(
        private PaymentRepositoryInterface $paymentRepository,
        private PaymentMethodRepositoryInterface $methodRepository,
        private PaymentProviderRegistry $registry,
        private PaymentLogger $logger,
        private MetricsService $metrics,
        private TenantContext $tenantContext,
    ) {}

    public function handle(ChargeTokenCommand $command): PaymentResultDTO
    {
        $method = $this->methodRepository->findById(PaymentMethodId::fromString($command->paymentMethodId));

        if (! $method instanceof PaymentMethod || ! $method->isActive()) {
            throw new PaymentException('Payment method not found or inactive', 404);
        }

        $provider = $this->registry->resolve($method->provider());

        if (! $provider instanceof SupportsTokenization) {
            throw new PaymentException("Provider {$method->provider()} does not support token charges");
        }

        $this->logger->info('Charging token', [
            'payment_method_id' => $command->paymentMethodId,
            'amount' => $command->amountKopecks,
            'provider' => $method->provider(),
        ]);

        return DB::transaction(function () use ($command, $method, $provider): PaymentResultDTO {
            $payment = Payment::create(
                id: PaymentId::generate(),
                amount: Money::ofRub($command->amountKopecks),
                description: $command->description,
                provider: $method->provider(),
                idempotencyKey: $command->idempotencyKey,
                metadata: array_merge($command->metadata, [
                    'user_id' => $command->userId,
                    'payment_method_id' => $command->paymentMethodId,
                    'recurring' => true,
                ]),
                tenantId: $this->tenantContext->has() ? $this->tenantContext->get() : null,
            );

            $this->paymentRepository->save($payment);

            $providerResponse = $provider->chargeToken(
                token: $method->token(),
                amount: Money::ofRub($command->amountKopecks),
                description: $command->description,
                idempotencyKey: $command->idempotencyKey,
            );

            $payment->assignExternalData(
                externalId: $providerResponse->externalId,
                confirmationUrl: $providerResponse->confirmationUrl,
                paymentMethodId: $method->id()->toString(),
            );

            if ($providerResponse->status === 'succeeded') {
                $payment->markAsSucceeded($providerResponse->externalId);
            }

            $this->paymentRepository->save($payment);
            $this->metrics->paymentCreated($method->provider());

            return PaymentResultDTO::fromAggregate($payment);
        });
    }
}
