<?php

declare(strict_types=1);

namespace App\Payments\Domain\Aggregates;

use App\Payments\Domain\Enums\DisputeStatus;
use App\Payments\Domain\Events\DisputeWasFiled;
use App\Payments\Domain\Events\DisputeWasResolved;
use App\Payments\Domain\Events\DomainEvent;
use App\Payments\Domain\Exceptions\InvalidPaymentStateException;
use App\Payments\Domain\ValueObjects\DisputeId;
use App\Payments\Domain\ValueObjects\Money;
use App\Payments\Domain\ValueObjects\PaymentId;

final class Dispute
{
    /** @var DomainEvent[] */
    private array $domainEvents = [];

    private function __construct(
        private readonly DisputeId $id,
        private readonly PaymentId $paymentId,
        private DisputeStatus $status,
        private readonly Money $amount,
        private readonly string $reason,
        private ?string $note = null,
    ) {}

    public static function file(
        DisputeId $id,
        PaymentId $paymentId,
        Money $amount,
        string $reason,
    ): self {
        $dispute = new self(
            id: $id,
            paymentId: $paymentId,
            status: DisputeStatus::Filed,
            amount: $amount,
            reason: $reason,
        );

        $dispute->recordEvent(new DisputeWasFiled(
            disputeId: $id->toString(),
            paymentId: $paymentId->toString(),
            amountKopecks: $amount->amount(),
            reason: $reason,
        ));

        return $dispute;
    }

    public function markAsWon(?string $note = null): void
    {
        $this->guardAgainstResolved();

        $this->status = DisputeStatus::Won;
        $this->note   = $note;

        $this->recordEvent(new DisputeWasResolved(
            disputeId: $this->id->toString(),
            paymentId: $this->paymentId->toString(),
            resolution: DisputeStatus::Won->value,
            note: $note,
        ));
    }

    public function markAsLost(?string $note = null): void
    {
        $this->guardAgainstResolved();

        $this->status = DisputeStatus::Lost;
        $this->note   = $note;

        $this->recordEvent(new DisputeWasResolved(
            disputeId: $this->id->toString(),
            paymentId: $this->paymentId->toString(),
            resolution: DisputeStatus::Lost->value,
            note: $note,
        ));
    }

    public static function restore(
        DisputeId $id,
        PaymentId $paymentId,
        DisputeStatus $status,
        Money $amount,
        string $reason,
        ?string $note = null,
    ): self {
        return new self(
            id: $id,
            paymentId: $paymentId,
            status: $status,
            amount: $amount,
            reason: $reason,
            note: $note,
        );
    }

    /** @return DomainEvent[] */
    public function pullDomainEvents(): array
    {
        $events            = $this->domainEvents;
        $this->domainEvents = [];

        return $events;
    }

    public function id(): DisputeId
    {
        return $this->id;
    }

    public function paymentId(): PaymentId
    {
        return $this->paymentId;
    }

    public function status(): DisputeStatus
    {
        return $this->status;
    }

    public function amount(): Money
    {
        return $this->amount;
    }

    public function reason(): string
    {
        return $this->reason;
    }

    public function note(): ?string
    {
        return $this->note;
    }

    private function recordEvent(DomainEvent $event): void
    {
        $this->domainEvents[] = $event;
    }

    private function guardAgainstResolved(): void
    {
        if ($this->status->isResolved()) {
            throw new InvalidPaymentStateException(
                "Dispute {$this->id->toString()} is already resolved: {$this->status->value}"
            );
        }
    }
}
