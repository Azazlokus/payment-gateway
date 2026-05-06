<?php

declare(strict_types=1);

namespace App\CryptoPayments\Domain\Events;

final readonly class RefundWasRequested extends DomainEvent
{
    public function __construct(
        public readonly string $refundId,
        public readonly string $depositId,
        public readonly string $toAddress,
        public readonly int $amountUnits,
        public readonly string $asset,
    ) {
        parent::__construct();
    }

    public function eventName(): string
    {
        return 'crypto.refund.requested';
    }
}
