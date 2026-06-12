<?php

declare(strict_types=1);

namespace App\Contexts\CryptoPayments\Domain\Events;

final readonly class RefundWasRequested extends DomainEvent
{
    public function __construct(
        public string $refundId,
        public string $depositId,
        public string $toAddress,
        public int $amountUnits,
        public string $asset,
    ) {
        parent::__construct();
    }

    public function eventName(): string
    {
        return 'crypto.refund.requested';
    }
}
