<?php

declare(strict_types=1);

namespace App\Contexts\CryptoPayments\Domain\ValueObjects;

use DateTimeImmutable;

final readonly class TransactionResult
{
    public function __construct(
        public TxHash $hash,
        public NativeCryptoAmount $actualAmount,
        public DateTimeImmutable $confirmedAt,
    ) {}
}
