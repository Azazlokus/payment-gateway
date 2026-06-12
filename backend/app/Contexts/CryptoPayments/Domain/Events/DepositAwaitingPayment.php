<?php

declare(strict_types=1);

namespace App\Contexts\CryptoPayments\Domain\Events;

final readonly class DepositAwaitingPayment extends DomainEvent
{
    public function __construct(
        public string $depositId,
        public string $paymentId,
        public string $asset,
        public int $expectedUnits,
        public string $memo,
        public string $depositAddress,
        public string $expiresAt,
    ) {
        parent::__construct();
    }

    public function eventName(): string
    {
        return 'crypto_deposit.awaiting';
    }
}
