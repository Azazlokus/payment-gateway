<?php

declare(strict_types=1);

namespace App\Contexts\CryptoPayments\Domain\ValueObjects;

final readonly class DepositCredentials
{
    public function __construct(
        public CryptoAddress $depositAddress,
        public ?Memo $memo,
        public string $qrPayload,
    ) {}
}
