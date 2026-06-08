<?php

declare(strict_types=1);

namespace App\Payments\Domain\Events;

final readonly class PaymentWasCaptured extends DomainEvent
{
    public function __construct(
        public readonly string $paymentId,
        public readonly int $capturedAmountKopecks,
        public readonly string $provider,
    ) {
        parent::__construct();
    }

    public function eventName(): string
    {
        return 'payment.captured';
    }
}
