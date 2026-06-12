<?php

declare(strict_types=1);

namespace App\Contexts\Payments\Domain\Events;

final readonly class PaymentWasCreated extends DomainEvent
{
    public function __construct(
        public string $paymentId,
        public int $amount,
        public string $currency,
        public string $description,
        public string $provider,
        public string $idempotencyKey,
        /** @var array<string, mixed> */
        public array $metadata = [],
    ) {
        parent::__construct();
    }

    public function eventName(): string
    {
        return 'payment.created';
    }
}
