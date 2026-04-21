<?php

declare(strict_types=1);

namespace App\CryptoPayments\Application\DTOs;

use App\CryptoPayments\Domain\Aggregates\CryptoDeposit;
use App\CryptoPayments\Domain\Enums\CryptoAsset;
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
        public ?string $memo,
        public string $expiresAt,
        public string $qrPayload,
        public ?string $txHash,
    ) {}

    public static function fromAggregate(CryptoDeposit $deposit): self
    {
        $address = $deposit->depositAddress()->toString();
        $memoStr = $deposit->memo()?->toString();
        $units   = $deposit->expectedAmount()->units();

        $qrPayload = match ($deposit->asset()) {
            CryptoAsset::TON      => "ton://transfer/{$address}?amount={$units}&text={$memoStr}",
            CryptoAsset::USDT_TON => "ton://transfer/{$address}?text={$memoStr}",
            CryptoAsset::BTC      => sprintf('bitcoin:%s?amount=%s', $address, number_format($units / 1e8, 8, '.', '')),
            CryptoAsset::TRX,
            CryptoAsset::USDT_TRC20 => "tron:{$address}",
        };

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
