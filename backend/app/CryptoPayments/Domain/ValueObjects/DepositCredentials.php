<?php

declare(strict_types=1);

namespace App\CryptoPayments\Domain\ValueObjects;

final readonly class DepositCredentials
{
    public function __construct(
        public TonAddress $depositAddress,
        public Memo $memo,
        public string $qrPayload,
    ) {}
}
