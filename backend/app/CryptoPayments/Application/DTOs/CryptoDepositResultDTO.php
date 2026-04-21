<?php

declare(strict_types=1);

namespace App\CryptoPayments\Application\DTOs;

use App\CryptoPayments\Domain\Aggregates\CryptoDeposit;
use DateTimeInterface;

final readonly class CryptoDepositResultDTO
{
    public function __construct(
        public string $depositId,
        public string $paymentId,
        public string $status,
        public string $asset,
        public int $expectedUnits,
        public string $cryptoAmount,
        public int $fiatAmountKopecks,
        public string $depositAddress,
        public string $memo,
        public string $expiresAt,
        public string $qrPayload,
        public ?string $txHash,
    ) {}

    public static function fromAggregate(CryptoDeposit $deposit): self
    {
        $address    = $deposit->depositAddress()->toNonBounceable()->toString();
        $nanotons   = $deposit->expectedAmount()->units();
        $memoStr    = $deposit->memo()->toString();
        $qrPayload  = "ton://transfer/{$address}?amount={$nanotons}&text={$memoStr}";

        return new self(
            depositId: $deposit->id()->toString(),
            paymentId: $deposit->paymentId(),
            status: $deposit->status()->value,
            asset: $deposit->asset()->value,
            expectedUnits: $deposit->expectedAmount()->units(),
            cryptoAmount: $deposit->expectedAmount()->humanReadable(),
            fiatAmountKopecks: $deposit->fiatAmountKopecks(),
            depositAddress: $address,
            memo: $memoStr,
            expiresAt: $deposit->expiresAt()->format(DateTimeInterface::ATOM),
            qrPayload: $qrPayload,
            txHash: $deposit->txHash()?->toString(),
        );
    }
}
