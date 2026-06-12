<?php

declare(strict_types=1);

namespace App\Contexts\CryptoPayments\Domain\Events;

final readonly class DepositConfirmed extends DomainEvent
{
    public function __construct(
        public string $depositId,
        public string $paymentId,
        public string $txHash,
        public int $actualUnits,
    ) {
        parent::__construct();
    }

    public function eventName(): string
    {
        return 'crypto_deposit.confirmed';
    }
}
