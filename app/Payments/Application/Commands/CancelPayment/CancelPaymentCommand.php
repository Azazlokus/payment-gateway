<?php

declare(strict_types=1);

namespace App\Payments\Application\Commands\CancelPayment;

final readonly class CancelPaymentCommand
{
    public function __construct(
        public string $paymentId,
        public string $reason = 'Отменено пользователем',
    ) {}
}
