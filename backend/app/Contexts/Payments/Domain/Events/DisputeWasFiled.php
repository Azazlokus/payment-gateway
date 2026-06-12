<?php

declare(strict_types=1);

namespace App\Contexts\Payments\Domain\Events;

final readonly class DisputeWasFiled extends DomainEvent
{
    public function __construct(
        public readonly string $disputeId,
        public readonly string $paymentId,
        public readonly int $amountKopecks,
        public readonly string $reason,
    ) {
        parent::__construct();
    }

    public function eventName(): string
    {
        return 'dispute.filed';
    }
}
