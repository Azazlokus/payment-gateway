<?php

declare(strict_types=1);

namespace App\Contexts\CryptoPayments\Domain\Events;

final readonly class RefundHasFailed extends DomainEvent
{
    public function __construct(
        public string $refundId,
        public string $reason,
    ) {
        parent::__construct();
    }

    public function eventName(): string
    {
        return 'crypto.refund.failed';
    }
}
