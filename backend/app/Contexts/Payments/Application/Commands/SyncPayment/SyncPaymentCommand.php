<?php

declare(strict_types=1);

namespace App\Contexts\Payments\Application\Commands\SyncPayment;

final readonly class SyncPaymentCommand
{
    public function __construct(
        public string $paymentId,
    ) {}
}
