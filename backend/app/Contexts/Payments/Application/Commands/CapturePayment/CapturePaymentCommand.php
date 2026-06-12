<?php

declare(strict_types=1);

namespace App\Contexts\Payments\Application\Commands\CapturePayment;

final readonly class CapturePaymentCommand
{
    public function __construct(
        public string $paymentId,
        public ?int $amountKopecks = null,
    ) {}
}
