<?php

declare(strict_types=1);

namespace App\Contexts\Payments\Domain\Events;

final readonly class PaymentWasCaptured extends DomainEvent
{
    public function __construct(
        public string $paymentId,
        public int $capturedAmountKopecks,
        public string $provider,
    ) {
        parent::__construct();
    }

    public function eventName(): string
    {
        return 'payment.captured';
    }
}
