<?php

declare(strict_types=1);

namespace App\Contexts\CryptoPayments\Application\Commands\CreateCryptoRefund;

final readonly class CreateCryptoRefundCommand
{
    public function __construct(
        public readonly string $depositId,
        public readonly string $toAddress,
    ) {}
}
