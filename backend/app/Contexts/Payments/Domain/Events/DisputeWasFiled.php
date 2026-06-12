<?php

declare(strict_types=1);

namespace App\Contexts\Payments\Domain\Events;

final readonly class DisputeWasFiled extends DomainEvent
{
    public function __construct(
        public string $disputeId,
        public string $paymentId,
        public int $amountKopecks,
        public string $reason,
    ) {
        parent::__construct();
    }

    public function eventName(): string
    {
        return 'dispute.filed';
    }
}
