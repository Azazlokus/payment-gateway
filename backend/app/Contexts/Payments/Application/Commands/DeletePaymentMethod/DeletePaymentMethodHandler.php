<?php

declare(strict_types=1);

namespace App\Contexts\Payments\Application\Commands\DeletePaymentMethod;

use App\Contexts\Payments\Application\PaymentProviderRegistry;
use App\Contexts\Payments\Domain\Aggregates\PaymentMethod;
use App\Contexts\Payments\Domain\Contracts\PaymentMethodRepositoryInterface;
use App\Contexts\Payments\Domain\Contracts\SupportsTokenization;
use App\Contexts\Payments\Domain\Exceptions\PaymentException;
use App\Contexts\Payments\Domain\ValueObjects\PaymentMethodId;

final readonly class DeletePaymentMethodHandler
{
    public function __construct(
        private PaymentMethodRepositoryInterface $methodRepository,
        private PaymentProviderRegistry $registry,
    ) {}

    public function handle(DeletePaymentMethodCommand $command): void
    {
        $method = $this->methodRepository->findById(PaymentMethodId::fromString($command->paymentMethodId));

        if (! $method instanceof PaymentMethod) {
            throw new PaymentException('Payment method not found', 404);
        }

        $provider = $this->registry->resolve($method->provider());

        if ($provider instanceof SupportsTokenization) {
            $provider->deleteToken($method->token());
        }

        $method->deactivate();
        $this->methodRepository->save($method);
    }
}
