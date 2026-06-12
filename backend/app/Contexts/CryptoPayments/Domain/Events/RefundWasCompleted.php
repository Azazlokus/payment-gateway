<?php

declare(strict_types=1);

namespace App\Contexts\CryptoPayments\Domain\Events;

final readonly class RefundWasCompleted extends DomainEvent
{
    public function __construct(
        public string $refundId,
        public string $txHash,
    ) {
        parent::__construct();
    }

    public function eventName(): string
    {
        return 'crypto.refund.completed';
    }
}
