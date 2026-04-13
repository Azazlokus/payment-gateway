<?php

declare(strict_types=1);

namespace App\Payments\Domain\Events;

final readonly class PaymentWasRefunded extends DomainEvent
{
    public function __construct(
        public readonly string $paymentId,
        public readonly int    $refundAmount,
        public readonly string $reason = '',
    ) {
        parent::__construct();
    }

    public function eventName(): string
    {
        return 'payment.refunded';
    }
}
