<?php

declare(strict_types=1);

namespace App\Payments\Application\Commands\RefundPayment;

final readonly class RefundPaymentCommand
{
    public function __construct(
        public string $paymentId,
        public ?int $amountKopecks,
        public string $reason = '',
    ) {}
}
