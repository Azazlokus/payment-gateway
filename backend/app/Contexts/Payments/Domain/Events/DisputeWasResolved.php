<?php

declare(strict_types=1);

namespace App\Contexts\Payments\Domain\Events;

final readonly class DisputeWasResolved extends DomainEvent
{
    public function __construct(
        public string $disputeId,
        public string $paymentId,
        public string $resolution, // 'Won' | 'Lost'
        public ?string $note,
    ) {
        parent::__construct();
    }

    public function eventName(): string
    {
        return 'dispute.resolved';
    }
}
