<?php

declare(strict_types=1);

namespace App\Contexts\CryptoPayments\Application\Commands\CreateCryptoDeposit;

use App\Contexts\CryptoPayments\Domain\Enums\CryptoAsset;

final readonly class CreateCryptoDepositCommand
{
    public function __construct(
        public string $paymentId,
        public int $fiatAmountKopecks,
        public CryptoAsset $asset,
    ) {}
}
