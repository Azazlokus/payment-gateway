<?php

declare(strict_types=1);

namespace App\Contexts\Payments\Domain\Events;

final readonly class PaymentRequiresThreeDSecure extends DomainEvent
{
    public function __construct(
        public readonly string $paymentId,
        public readonly string $challengeUrl,
        public readonly string $provider,
    ) {
        parent::__construct();
    }

    public function eventName(): string
    {
        return 'payment.three_ds_required';
    }
}
