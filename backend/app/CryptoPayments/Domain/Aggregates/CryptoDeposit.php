<?php

declare(strict_types=1);

namespace App\CryptoPayments\Domain\Aggregates;

use App\CryptoPayments\Domain\Enums\CryptoAsset;
use App\CryptoPayments\Domain\Enums\CryptoDepositStatus;
use App\CryptoPayments\Domain\Events\DepositAwaitingPayment;
use App\CryptoPayments\Domain\Events\DepositConfirmed;
use App\CryptoPayments\Domain\Events\DepositExpired;
use App\CryptoPayments\Domain\Events\DepositOverpaid;
use App\CryptoPayments\Domain\Events\DomainEvent;
use App\CryptoPayments\Domain\Events\TransactionDetected;
use App\CryptoPayments\Domain\Exceptions\DepositExpiredException;
use App\CryptoPayments\Domain\ValueObjects\CryptoAddress;
use App\CryptoPayments\Domain\ValueObjects\CryptoDepositId;
use App\CryptoPayments\Domain\ValueObjects\Memo;
use App\CryptoPayments\Domain\ValueObjects\NativeCryptoAmount;
use App\CryptoPayments\Domain\ValueObjects\TxHash;
use DateTimeImmutable;
use DateTimeInterface;

final class CryptoDeposit
{
    /** @var DomainEvent[] */
    private array $domainEvents = [];

    private function __construct(
        private readonly CryptoDepositId $id,
        private readonly string $paymentId,
        private CryptoDepositStatus $status,
        private readonly CryptoAsset $asset,
        private readonly NativeCryptoAmount $expectedAmount,
        private readonly int $fiatAmountKopecks,
        private readonly CryptoAddress $depositAddress,
        private readonly ?Memo $memo,
        private readonly DateTimeImmutable $expiresAt,
        private readonly int $createdAtTimestamp,
        private ?TxHash $txHash = null,
        private ?NativeCryptoAmount $actualAmount = null,
    ) {}

    public static function create(
        CryptoDepositId $id,
        string $paymentId,
        CryptoAsset $asset,
        NativeCryptoAmount $expectedAmount,
        int $fiatAmountKopecks,
        CryptoAddress $depositAddress,
        ?Memo $memo,
        DateTimeImmutable $expiresAt,
    ): self {
        $deposit = new self(
            id: $id,
            paymentId: $paymentId,
            status: CryptoDepositStatus::Awaiting,
            asset: $asset,
            expectedAmount: $expectedAmount,
            fiatAmountKopecks: $fiatAmountKopecks,
            depositAddress: $depositAddress,
            memo: $memo,
            expiresAt: $expiresAt,
            createdAtTimestamp: (new DateTimeImmutable)->getTimestamp(),
        );

        $deposit->recordEvent(new DepositAwaitingPayment(
            depositId: $id->toString(),
            paymentId: $paymentId,
            asset: $asset->value,
            expectedUnits: $expectedAmount->units(),
            memo: $memo?->toString() ?? '',
            depositAddress: $depositAddress->toString(),
            expiresAt: $expiresAt->format(DateTimeInterface::ATOM),
        ));

        return $deposit;
    }

    public function detectTransaction(TxHash $hash, NativeCryptoAmount $actual): void
    {
        if ($this->status->isTerminal()) {
            return; // idempotent
        }

        if ($this->isExpired()) {
            throw new DepositExpiredException($this->id->toString());
        }

        $this->txHash = $hash;
        $this->actualAmount = $actual;

        if ($actual->units() > $this->expectedAmount->units()) {
            $this->status = CryptoDepositStatus::Overpaid;
            $this->recordEvent(new DepositOverpaid(
                depositId: $this->id->toString(),
                paymentId: $this->paymentId,
                expectedUnits: $this->expectedAmount->units(),
                actualUnits: $actual->units(),
            ));
        } else {
            $this->status = CryptoDepositStatus::Detected;
            $this->recordEvent(new TransactionDetected(
                depositId: $this->id->toString(),
                txHash: $hash->toString(),
                actualUnits: $actual->units(),
            ));
        }
    }

    public function confirm(TxHash $hash, NativeCryptoAmount $actual): void
    {
        if ($this->status === CryptoDepositStatus::Confirmed) {
            return; // idempotent
        }

        if ($this->status === CryptoDepositStatus::Expired) {
            throw new DepositExpiredException($this->id->toString());
        }

        $this->txHash = $hash;
        $this->actualAmount = $actual;
        $this->status = CryptoDepositStatus::Confirmed;

        $this->recordEvent(new DepositConfirmed(
            depositId: $this->id->toString(),
            paymentId: $this->paymentId,
            txHash: $hash->toString(),
            actualUnits: $actual->units(),
        ));
    }

    public function expire(): void
    {
        if ($this->status->isTerminal()) {
            return;
        }

        $this->status = CryptoDepositStatus::Expired;
        $this->recordEvent(new DepositExpired(
            depositId: $this->id->toString(),
            paymentId: $this->paymentId,
        ));
    }

    private function isExpired(): bool
    {
        return new DateTimeImmutable > $this->expiresAt;
    }

    private function recordEvent(DomainEvent $event): void
    {
        $this->domainEvents[] = $event;
    }

    /** @return DomainEvent[] */
    public function pullDomainEvents(): array
    {
        $events = $this->domainEvents;
        $this->domainEvents = [];

        return $events;
    }

    public static function restore(
        CryptoDepositId $id,
        string $paymentId,
        CryptoDepositStatus $status,
        CryptoAsset $asset,
        NativeCryptoAmount $expectedAmount,
        int $fiatAmountKopecks,
        CryptoAddress $depositAddress,
        ?Memo $memo,
        DateTimeImmutable $expiresAt,
        int $createdAtTimestamp,
        ?TxHash $txHash = null,
        ?NativeCryptoAmount $actualAmount = null,
    ): self {
        return new self(
            id: $id,
            paymentId: $paymentId,
            status: $status,
            asset: $asset,
            expectedAmount: $expectedAmount,
            fiatAmountKopecks: $fiatAmountKopecks,
            depositAddress: $depositAddress,
            memo: $memo,
            expiresAt: $expiresAt,
            createdAtTimestamp: $createdAtTimestamp,
            txHash: $txHash,
            actualAmount: $actualAmount,
        );
    }

    // ─── Getters ──────────────────────────────────────────────────────────────

    public function id(): CryptoDepositId
    {
        return $this->id;
    }

    public function paymentId(): string
    {
        return $this->paymentId;
    }

    public function status(): CryptoDepositStatus
    {
        return $this->status;
    }

    public function asset(): CryptoAsset
    {
        return $this->asset;
    }

    public function expectedAmount(): NativeCryptoAmount
    {
        return $this->expectedAmount;
    }

    public function fiatAmountKopecks(): int
    {
        return $this->fiatAmountKopecks;
    }

    public function depositAddress(): CryptoAddress
    {
        return $this->depositAddress;
    }

    public function memo(): ?Memo
    {
        return $this->memo;
    }

    public function expiresAt(): DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function createdAtTimestamp(): int
    {
        return $this->createdAtTimestamp;
    }

    public function createdAt(): DateTimeImmutable
    {
        return new DateTimeImmutable('@'.$this->createdAtTimestamp);
    }

    public function txHash(): ?TxHash
    {
        return $this->txHash;
    }

    public function actualAmount(): ?NativeCryptoAmount
    {
        return $this->actualAmount;
    }
}
