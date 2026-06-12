<?php

declare(strict_types=1);

namespace App\Contexts\CryptoPayments\Domain\Aggregates;

use App\Contexts\CryptoPayments\Domain\Enums\CryptoAsset;
use App\Contexts\CryptoPayments\Domain\Enums\CryptoRefundStatus;
use App\Contexts\CryptoPayments\Domain\Events\DomainEvent;
use App\Contexts\CryptoPayments\Domain\Events\RefundHasFailed;
use App\Contexts\CryptoPayments\Domain\Events\RefundWasCompleted;
use App\Contexts\CryptoPayments\Domain\Events\RefundWasRequested;
use App\Contexts\CryptoPayments\Domain\ValueObjects\CryptoAddress;
use App\Contexts\CryptoPayments\Domain\ValueObjects\CryptoRefundId;
use App\Contexts\CryptoPayments\Domain\ValueObjects\NativeCryptoAmount;
use App\Contexts\CryptoPayments\Domain\ValueObjects\TxHash;

final class CryptoRefundRequest
{
    /** @var list<DomainEvent> */
    private array $events = [];

    private function __construct(
        private readonly CryptoRefundId $id,
        private readonly string $depositId,
        private readonly CryptoAddress $toAddress,
        private readonly NativeCryptoAmount $amount,
        private readonly CryptoAsset $asset,
        private CryptoRefundStatus $status,
        private ?TxHash $txHash = null,
        private ?string $failureReason = null,
    ) {}

    public static function create(
        string $depositId,
        CryptoAddress $toAddress,
        NativeCryptoAmount $amount,
        CryptoAsset $asset,
    ): self {
        $self = new self(
            id: CryptoRefundId::generate(),
            depositId: $depositId,
            toAddress: $toAddress,
            amount: $amount,
            asset: $asset,
            status: CryptoRefundStatus::Pending,
        );

        $self->events[] = new RefundWasRequested(
            refundId: $self->id->toString(),
            depositId: $depositId,
            toAddress: $toAddress->toString(),
            amountUnits: $amount->units(),
            asset: $asset->value,
        );

        return $self;
    }

    public static function restore(
        string $id,
        string $depositId,
        string $toAddress,
        int $amountUnits,
        string $asset,
        string $status,
        ?string $txHash,
        ?string $failureReason,
    ): self {
        $cryptoAsset = CryptoAsset::from($asset);

        return new self(
            id: CryptoRefundId::fromString($id),
            depositId: $depositId,
            toAddress: CryptoAddress::fromString($toAddress),
            amount: NativeCryptoAmount::of($amountUnits, $cryptoAsset),
            asset: $cryptoAsset,
            status: CryptoRefundStatus::from($status),
            txHash: $txHash !== null ? TxHash::fromString($txHash) : null,
            failureReason: $failureReason,
        );
    }

    public function markAsBroadcasting(): void
    {
        if ($this->status !== CryptoRefundStatus::Pending) {
            throw new \LogicException("Cannot broadcast refund {$this->id->toString()}: status is {$this->status->value}");
        }

        $this->status = CryptoRefundStatus::Broadcasting;
    }

    public function markAsCompleted(TxHash $hash): void
    {
        $this->txHash = $hash;
        $this->status = CryptoRefundStatus::Completed;
        $this->events[] = new RefundWasCompleted(
            refundId: $this->id->toString(),
            txHash: $hash->toString(),
        );
    }

    public function markAsFailed(string $reason): void
    {
        $this->failureReason = $reason;
        $this->status = CryptoRefundStatus::Failed;
        $this->events[] = new RefundHasFailed(
            refundId: $this->id->toString(),
            reason: $reason,
        );
    }

    // ─── Getters ──────────────────────────────────────────────────────────────

    public function id(): CryptoRefundId
    {
        return $this->id;
    }

    public function depositId(): string
    {
        return $this->depositId;
    }

    public function toAddress(): CryptoAddress
    {
        return $this->toAddress;
    }

    public function amount(): NativeCryptoAmount
    {
        return $this->amount;
    }

    public function asset(): CryptoAsset
    {
        return $this->asset;
    }

    public function status(): CryptoRefundStatus
    {
        return $this->status;
    }

    public function txHash(): ?TxHash
    {
        return $this->txHash;
    }

    public function failureReason(): ?string
    {
        return $this->failureReason;
    }

    /** @return list<DomainEvent> */
    public function pullDomainEvents(): array
    {
        $events = $this->events;
        $this->events = [];

        return $events;
    }
}
