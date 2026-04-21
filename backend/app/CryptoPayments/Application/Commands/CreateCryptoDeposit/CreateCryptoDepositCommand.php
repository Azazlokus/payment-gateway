<?php

declare(strict_types=1);

namespace App\CryptoPayments\Application\Commands\CreateCryptoDeposit;

use App\CryptoPayments\Domain\Enums\CryptoAsset;

final readonly class CreateCryptoDepositCommand
{
    public function __construct(
        public string $paymentId,
        public int $fiatAmountKopecks,
        public CryptoAsset $asset,
    ) {}
}
