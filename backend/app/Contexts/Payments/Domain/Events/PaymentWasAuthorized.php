<?php

declare(strict_types=1);

namespace App\Contexts\Payments\Domain\Events;

final readonly class PaymentWasAuthorized extends DomainEvent
{
    public function __construct(
        public readonly string $paymentId,
        public readonly string $externalId,
        public readonly string $provider,
    ) {
        parent::__construct();
    }

    public function eventName(): string
    {
        return 'payment.authorized';
    }
}
