<?php

declare(strict_types=1);

namespace App\Contexts\Payments\Domain\Events;

final readonly class PaymentMethodWasDeleted extends DomainEvent
{
    public function __construct(
        public string $paymentMethodId,
        public string $customerId,
    ) {
        parent::__construct();
    }

    public function eventName(): string
    {
        return 'payment_method.deleted';
    }
}
