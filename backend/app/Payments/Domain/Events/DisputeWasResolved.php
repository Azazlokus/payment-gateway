<?php

declare(strict_types=1);

namespace App\Payments\Domain\Events;

use App\Payments\Domain\Enums\DisputeStatus;

final readonly class DisputeWasResolved extends DomainEvent
{
    public function __construct(
        public readonly string $disputeId,
        public readonly string $paymentId,
        public readonly string $resolution, // 'Won' | 'Lost'
        public readonly ?string $note,
    ) {
        parent::__construct();
    }

    public function eventName(): string
    {
        return 'dispute.resolved';
    }
}
