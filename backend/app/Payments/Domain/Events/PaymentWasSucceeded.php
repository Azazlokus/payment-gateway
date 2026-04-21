<?php

declare(strict_types=1);

namespace App\Payments\Domain\Events;

final readonly class PaymentWasSucceeded extends DomainEvent
{
    public function __construct(
        public readonly string $paymentId,
        public readonly string $externalId,
    ) {
        parent::__construct();
    }

    public function eventName(): string
    {
        return 'payment.succeeded';
    }
}
