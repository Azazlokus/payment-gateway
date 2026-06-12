<?php

declare(strict_types=1);

namespace App\Contexts\CryptoPayments\Domain\Events;

final readonly class DepositExpired extends DomainEvent
{
    public function __construct(
        public string $depositId,
        public string $paymentId,
    ) {
        parent::__construct();
    }

    public function eventName(): string
    {
        return 'crypto_deposit.expired';
    }
}
