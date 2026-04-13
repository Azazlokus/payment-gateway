<?php

declare(strict_types=1);

namespace App\Payments\Domain\Events;

final readonly class PaymentWasCreated extends DomainEvent
{
    public function __construct(
        public string $paymentId,
        public int $amount,
        public string $currency,
        public string $description,
        public string $provider,
        public string $idempotencyKey,
        public array $metadata = [],
    ) {
        parent::__construct();
    }

    public function eventName(): string
    {
        return 'payment.created';
    }
}
