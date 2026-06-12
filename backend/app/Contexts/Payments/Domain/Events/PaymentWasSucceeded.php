<?php

declare(strict_types=1);

namespace App\Contexts\Payments\Domain\Events;

final readonly class PaymentWasSucceeded extends DomainEvent
{
    public function __construct(
        public string $paymentId,
        public string $externalId,
    ) {
        parent::__construct();
    }

    public function eventName(): string
    {
        return 'payment.succeeded';
    }
}
