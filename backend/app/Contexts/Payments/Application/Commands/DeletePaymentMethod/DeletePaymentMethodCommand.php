<?php

declare(strict_types=1);

namespace App\Contexts\Payments\Application\Commands\DeletePaymentMethod;

final readonly class DeletePaymentMethodCommand
{
    public function __construct(
        public string $paymentMethodId,
    ) {}
}
