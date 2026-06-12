<?php

declare(strict_types=1);

namespace App\Contexts\Payments\Domain\Events;

final readonly class PaymentWasCancelled extends DomainEvent
{
    public function __construct(
        public string $paymentId,
        public string $reason,
    ) {
        parent::__construct();
    }

    public function eventName(): string
    {
        return 'payment.cancelled';
    }
}
