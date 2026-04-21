<?php

declare(strict_types=1);

namespace App\CryptoPayments\Domain\Events;

final readonly class DepositOverpaid extends DomainEvent
{
    public function __construct(
        public string $depositId,
        public string $paymentId,
        public int $expectedUnits,
        public int $actualUnits,
    ) {
        parent::__construct();
    }

    public function eventName(): string
    {
        return 'crypto_deposit.overpaid';
    }
}
