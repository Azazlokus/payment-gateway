<?php

declare(strict_types=1);

namespace App\Contexts\Payments\Application\Commands\TokenizePaymentMethod;

use App\Contexts\Payments\Application\PaymentProviderRegistry;
use App\Contexts\Payments\Domain\Aggregates\PaymentMethod;
use App\Contexts\Payments\Domain\Contracts\PaymentMethodRepositoryInterface;
use App\Contexts\Payments\Domain\Contracts\PaymentRepositoryInterface;
use App\Contexts\Payments\Domain\Contracts\SupportsTokenization;
use App\Contexts\Payments\Domain\Enums\PaymentMethodType;
use App\Contexts\Payments\Domain\Exceptions\PaymentException;
use App\Contexts\Payments\Domain\ValueObjects\CardFingerprint;
use App\Contexts\Payments\Domain\ValueObjects\PaymentId;
use App\Contexts\Payments\Domain\ValueObjects\PaymentMethodId;
use App\Contexts\Payments\Infrastructure\Tenant\TenantContext;

final readonly class TokenizePaymentMethodHandler
{
    public function __construct(
        private PaymentRepositoryInterface $paymentRepository,
        private PaymentMethodRepositoryInterface $methodRepository,
        private PaymentProviderRegistry $registry,
        private TenantContext $tenantContext,
    ) {}

    public function handle(TokenizePaymentMethodCommand $command): PaymentMethod
    {
        $payment = $this->paymentRepository->findById(PaymentId::fromString($command->paymentId));

        if ($payment === null) {
            throw new PaymentException('Payment not found', 404);
        }

        $provider = $this->registry->resolve($payment->provider());

        if (! $provider instanceof SupportsTokenization) {
            throw new PaymentException("Provider {$payment->provider()} does not support tokenization");
        }

        $result = $provider->tokenize($command->paymentId);

        [$expiresMonth, $expiresYear] = str_contains((string) $result->expiresAt, '/')
            ? explode('/', (string) $result->expiresAt, 2)
            : ['', (string) $result->expiresAt];

        $fingerprint = CardFingerprint::compute(
            last4: $result->last4,
            brand: $result->brand,
            expiresMonth: $expiresMonth,
            expiresYear: $expiresYear,
        );

        $existing = $this->methodRepository->findByFingerprint($command->customerId, $fingerprint->toString());

        if ($existing !== null) {
            if (! $existing->isActive()) {
                $existing->reactivate($result->token, $result->last4, $result->brand, $result->expiresAt);
                $this->methodRepository->save($existing);
            }

            return $existing;
        }

        $method = PaymentMethod::create(
            id: PaymentMethodId::generate(),
            tenantId: $this->tenantContext->has() ? $this->tenantContext->get() : null,
            customerId: $command->customerId,
            provider: $payment->provider(),
            type: PaymentMethodType::from($result->type),
            token: $result->token,
            last4: $result->last4,
            brand: $result->brand,
            expiresAt: $result->expiresAt,
            fingerprint: $fingerprint,
        );

        $this->methodRepository->save($method);

        return $method;
    }
}
