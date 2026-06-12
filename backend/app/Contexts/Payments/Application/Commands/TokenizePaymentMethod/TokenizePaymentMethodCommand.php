<?php

declare(strict_types=1);

namespace App\Contexts\Payments\Application\Commands\TokenizePaymentMethod;

final readonly class TokenizePaymentMethodCommand
{
    public function __construct(
        public string $paymentId,
        public string $customerId,
    ) {}
}
