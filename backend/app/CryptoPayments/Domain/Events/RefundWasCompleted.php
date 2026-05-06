<?php

declare(strict_types=1);

namespace App\CryptoPayments\Domain\Events;

final readonly class RefundWasCompleted extends DomainEvent
{
    public function __construct(
        public readonly string $refundId,
        public readonly string $txHash,
    ) {
        parent::__construct();
    }

    public function eventName(): string
    {
        return 'crypto.refund.completed';
    }
}
