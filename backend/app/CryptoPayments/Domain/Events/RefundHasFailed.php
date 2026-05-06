<?php

declare(strict_types=1);

namespace App\CryptoPayments\Domain\Events;

final readonly class RefundHasFailed extends DomainEvent
{
    public function __construct(
        public readonly string $refundId,
        public readonly string $reason,
    ) {
        parent::__construct();
    }

    public function eventName(): string
    {
        return 'crypto.refund.failed';
    }
}
