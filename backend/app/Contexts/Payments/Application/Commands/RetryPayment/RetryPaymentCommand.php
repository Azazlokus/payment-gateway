<?php

declare(strict_types=1);

namespace App\Contexts\Payments\Application\Commands\RetryPayment;

final readonly class RetryPaymentCommand
{
    public function __construct(
        public string $paymentId,
        public string $returnUrl,
        public string $idempotencyKey,
    ) {}
}
