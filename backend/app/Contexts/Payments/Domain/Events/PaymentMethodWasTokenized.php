<?php

declare(strict_types=1);

namespace App\Contexts\Payments\Domain\Events;

final readonly class PaymentMethodWasTokenized extends DomainEvent
{
    public function __construct(
        public string $paymentMethodId,
        public string $customerId,
        public string $provider,
        public string $type,
        public string $last4,
    ) {
        parent::__construct();
    }

    public function eventName(): string
    {
        return 'payment_method.tokenized';
    }
}
