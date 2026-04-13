<?php

declare(strict_types=1);

namespace App\Payments\Domain\Aggregates;

use App\Payments\Domain\Entities\PaymentAttempt;
use App\Payments\Domain\Entities\RefundRequest;
use App\Payments\Domain\Enums\PaymentStatus;
use App\Payments\Domain\Events\DomainEvent;
use App\Payments\Domain\Events\PaymentWasCancelled;
use App\Payments\Domain\Events\PaymentWasCreated;
use App\Payments\Domain\Events\PaymentWasRefunded;
use App\Payments\Domain\Events\PaymentWasSucceeded;
use App\Payments\Domain\Exceptions\InvalidPaymentStateException;
use App\Payments\Domain\ValueObjects\AttemptId;
use App\Payments\Domain\ValueObjects\ExternalId;
use App\Payments\Domain\ValueObjects\Money;
use App\Payments\Domain\ValueObjects\PaymentId;
use App\Payments\Domain\ValueObjects\RefundId;

class Payment
{
    /** @var DomainEvent[] */
    private array $domainEvents = [];

    /** @var PaymentAttempt[] */
    private array $attempts = [];

    /** @var RefundRequest[] */
    private array $refundRequests = [];

    private function __construct(
        private readonly PaymentId $id,
        private Money $amount,
        private PaymentStatus $status,
        private readonly string $description,
        private readonly string $provider,
        private readonly string $idempotencyKey,
        private ?ExternalId $externalId = null,
        private ?string $confirmationUrl = null,
        private readonly array $metadata = [],
        private ?string $paymentMethodId = null, // ID сохранённого метода YooKassa
        private int $refundedAmountKopecks = 0,
    ) {}

    public static function create(
        PaymentId $id,
        Money $amount,
        string $description,
        string $provider,
        string $idempotencyKey,
        array $metadata = [],
    ) {
        $payment = new self(
            id: $id,
            amount: $amount,
            status: PaymentStatus::Pending,
            description: $description,
            provider: $provider,
            idempotencyKey: $idempotencyKey,
            metadata: $metadata,
        );

        $payment->recordEvent(new PaymentWasCreated(
            paymentId: $id->toString(),
            amount: $amount->amount(),
            currency: $amount->currency()->value,
            description: $description,
            provider: $provider,
            idempotencyKey: $idempotencyKey,
            metadata: $metadata,
        ));

        return $payment;

    }

    public function addAttempt(string $provider, Money $amount): PaymentAttempt
    {
        $attempt = new PaymentAttempt(
            id: AttemptId::generate(),
            amount: $amount,
            provider: $provider,
        );

        $this->attempts[] = $attempt;

        return $attempt;
    }

    public function requestRefund(Money $amount, string $reason): RefundRequest
    {
        // Бизнес-правило: нельзя рефандить больше доступного остатка
        $availableKopecks = $this->amount->amount() - $this->refundedAmountKopecks;
        if ($amount->amount() > $availableKopecks) {
            throw new InvalidPaymentStateException(
                "Refund amount {$amount->amount()} exceeds available amount {$availableKopecks}"
            );
        }

        // Бизнес-правило: только один pending-рефанд одновременно
        $hasPending = array_any(
            $this->refundRequests,
            fn (RefundRequest $r) => $r->isPending()
        );

        if ($hasPending) {
            throw new InvalidPaymentStateException('Refund already pending');
        }

        $refund = new RefundRequest(
            id: RefundId::generate(),
            amount: $amount,
            reason: $reason,
        );

        $this->refundRequests[] = $refund;

        $this->recordEvent(new PaymentWasRefunded(
            paymentId: $this->id->toString(),
            refundAmount: $amount->amount(),
            reason: $reason,
        ));

        return $refund;
    }

    /** @return PaymentAttempt[] */
    public function attempts(): array
    {
        return $this->attempts;
    }

    /** @return RefundRequest[] */
    public function refundRequests(): array
    {
        return $this->refundRequests;
    }

    public function markAsSucceeded(ExternalId $externalId): void
    {
        $this->guardAgainstTerminalStatus();

        $this->status = PaymentStatus::Succeeded;
        $this->externalId = $externalId;

        $this->recordEvent(new PaymentWasSucceeded(
            paymentId: $this->id->toString(),
            externalId: $externalId->toString(),
        ));
    }

    public function cancel(string $reason): void
    {
        $this->guardAgainstTerminalStatus();

        $this->status = PaymentStatus::Cancelled;

        $this->recordEvent(new PaymentWasCancelled(
            paymentId: $this->id->toString(),
            reason: $reason,
        ));
    }

    public function refund(Money $refundAmount): void
    {
        if ($this->status !== PaymentStatus::Succeeded) {
            throw new InvalidPaymentStateException(
                "Cannot refund payment in status: {$this->status->value}"
            );
        }

        $newRefundedTotal = $this->refundedAmountKopecks + $refundAmount->amount();

        if ($newRefundedTotal > $this->amount->amount()) {
            throw new InvalidPaymentStateException(
                "Refund would exceed payment amount: already refunded {$this->refundedAmountKopecks}, "
                ."trying to refund {$refundAmount->amount()} more, total {$this->amount->amount()}"
            );
        }

        $this->refundedAmountKopecks = $newRefundedTotal;

        // Переходим в Refunded только когда возвращена вся сумма
        if ($this->refundedAmountKopecks === $this->amount->amount()) {
            $this->status = PaymentStatus::Refunded;
        }

        $this->recordEvent(new PaymentWasRefunded(
            paymentId: $this->id->toString(),
            refundAmount: $refundAmount->amount(),
        ));
    }

    public function refundedAmountKopecks(): int
    {
        return $this->refundedAmountKopecks;
    }

    public function assignExternalData(ExternalId $externalId, string $confirmationUrl, ?string $paymentMethodId = null): void
    {
        $this->externalId = $externalId;
        $this->confirmationUrl = $confirmationUrl;
        $this->paymentMethodId = $paymentMethodId;
    }

    public function paymentMethodId(): ?string
    {
        return $this->paymentMethodId;
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

    private function guardAgainstTerminalStatus(): void
    {
        if ($this->status->isTerminal()) {
            throw new InvalidPaymentStateException(
                "Payment {$this->id->toString()} is already in terminal status: {$this->status->value}"
            );
        }
    }

    public function id(): PaymentId
    {
        return $this->id;
    }

    public function amount(): Money
    {
        return $this->amount;
    }

    public function status(): PaymentStatus
    {
        return $this->status;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function provider(): string
    {
        return $this->provider;
    }

    public function idempotencyKey(): string
    {
        return $this->idempotencyKey;
    }

    public function externalId(): ?ExternalId
    {
        return $this->externalId;
    }

    public function confirmationUrl(): ?string
    {
        return $this->confirmationUrl;
    }

    public function metadata(): array
    {
        return $this->metadata;
    }

    public static function restore(
        PaymentId $id,
        Money $amount,
        PaymentStatus $status,
        string $description,
        string $provider,
        string $idempotencyKey,
        ?ExternalId $externalId = null,
        ?string $confirmationUrl = null,
        array $metadata = [],
        ?string $paymentMethodId = null,
        int $refundedAmountKopecks = 0,
    ): self {
        return new self(
            id: $id,
            amount: $amount,
            status: $status,
            description: $description,
            provider: $provider,
            idempotencyKey: $idempotencyKey,
            externalId: $externalId,
            confirmationUrl: $confirmationUrl,
            metadata: $metadata,
            paymentMethodId: $paymentMethodId,
            refundedAmountKopecks: $refundedAmountKopecks,
        );
    }
}
