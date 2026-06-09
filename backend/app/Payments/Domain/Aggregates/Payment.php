<?php

declare(strict_types=1);

namespace App\Payments\Domain\Aggregates;

use App\Payments\Domain\Entities\PaymentAttempt;
use App\Payments\Domain\Entities\RefundRequest;
use App\Payments\Domain\Enums\PaymentStatus;
use App\Payments\Domain\Events\DomainEvent;
use App\Payments\Domain\Events\PaymentRequiresThreeDSecure;
use App\Payments\Domain\Events\PaymentWasAuthorized;
use App\Payments\Domain\Events\PaymentWasCancelled;
use App\Payments\Domain\Events\PaymentWasCaptured;
use App\Payments\Domain\Events\PaymentWasCreated;
use App\Payments\Domain\Events\PaymentWasRefunded;
use App\Payments\Domain\Events\PaymentWasSucceeded;
use App\Payments\Domain\Exceptions\InvalidPaymentStateException;
use App\Payments\Domain\ValueObjects\AttemptId;
use App\Payments\Domain\ValueObjects\ExternalId;
use App\Payments\Domain\ValueObjects\Money;
use App\Payments\Domain\ValueObjects\PaymentId;
use App\Payments\Domain\ValueObjects\RefundId;
use App\Payments\Domain\ValueObjects\SplitRule;

final class Payment
{
    /** @var DomainEvent[] */
    private array $domainEvents = [];

    /** @var PaymentAttempt[] */
    private array $attempts = [];

    /** @var RefundRequest[] */
    private array $refundRequests = [];

    /** @var SplitRule[] */
    private array $splits = [];

    private function __construct(
        private readonly PaymentId $id,
        private Money $amount,
        private PaymentStatus $status,
        private readonly string $description,
        private readonly string $provider,
        private readonly string $idempotencyKey,
        private ?ExternalId $externalId = null,
        private ?string $confirmationUrl = null,
        /** @var array<string, mixed> */
        private readonly array $metadata = [],
        private ?string $paymentMethodId = null, // ID сохранённого метода YooKassa
        private int $refundedAmountKopecks = 0,
        private int $capturedAmountKopecks = 0,
        private bool $threeDsRequired = false,
        private ?string $threeDsChallengeUrl = null,
    ) {}

    /**
     * @param  array<string, mixed>  $metadata
     * @param  SplitRule[]  $splits
     */
    public static function create(
        PaymentId $id,
        Money $amount,
        string $description,
        string $provider,
        string $idempotencyKey,
        array $metadata = [],
        array $splits = [],
    ): static {
        $payment = new self(
            id: $id,
            amount: $amount,
            status: PaymentStatus::Pending,
            description: $description,
            provider: $provider,
            idempotencyKey: $idempotencyKey,
            metadata: $metadata,
        );

        if ($splits !== []) {
            $payment->setSplits($splits);
        }

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

    /** @param SplitRule[] $splits */
    private function setSplits(array $splits): void
    {
        $totalSplitKopecks = 0;

        foreach ($splits as $split) {
            $totalSplitKopecks += $split->amount()->amount();
        }

        if ($totalSplitKopecks > $this->amount->amount()) {
            throw new InvalidPaymentStateException(
                "Splits total {$totalSplitKopecks} exceeds payment amount {$this->amount->amount()}"
            );
        }

        $this->splits = $splits;
    }

    /** @return SplitRule[] */
    public function splits(): array
    {
        return $this->splits;
    }

    public function hasSplits(): bool
    {
        return $this->splits !== [];
    }

    public function splitsTotal(): int
    {
        $total = 0;

        foreach ($this->splits as $split) {
            $total += $split->amount()->amount();
        }

        return $total;
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
        $availableKopecks = $this->refundableBase() - $this->refundedAmountKopecks;
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

    public function authorize(ExternalId $externalId): void
    {
        if ($this->status !== PaymentStatus::Pending) {
            throw new InvalidPaymentStateException(
                "Cannot authorize payment in status: {$this->status->value}"
            );
        }

        $this->status = PaymentStatus::Authorized;
        $this->externalId = $externalId;

        $this->recordEvent(new PaymentWasAuthorized(
            paymentId: $this->id->toString(),
            externalId: $externalId->toString(),
            provider: $this->provider,
        ));
    }

    public function capture(?Money $amount = null): void
    {
        if ($this->status !== PaymentStatus::Authorized) {
            throw new InvalidPaymentStateException(
                "Cannot capture payment in status: {$this->status->value}"
            );
        }

        $captureAmount = $amount ?? $this->amount;

        if ($captureAmount->amount() > $this->amount->amount()) {
            throw new InvalidPaymentStateException(
                "Capture amount {$captureAmount->amount()} exceeds authorized amount {$this->amount->amount()}"
            );
        }

        $this->capturedAmountKopecks = $captureAmount->amount();
        $this->status = PaymentStatus::Succeeded;

        $this->recordEvent(new PaymentWasCaptured(
            paymentId: $this->id->toString(),
            capturedAmountKopecks: $this->capturedAmountKopecks,
            provider: $this->provider,
        ));
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

        $refundableBase = $this->refundableBase();
        $newRefundedTotal = $this->refundedAmountKopecks + $refundAmount->amount();

        if ($newRefundedTotal > $refundableBase) {
            throw new InvalidPaymentStateException(
                "Refund would exceed payment amount: already refunded {$this->refundedAmountKopecks}, "
                ."trying to refund {$refundAmount->amount()} more, total {$refundableBase}"
            );
        }

        $this->refundedAmountKopecks = $newRefundedTotal;

        if ($this->refundedAmountKopecks === $refundableBase) {
            $this->status = PaymentStatus::Refunded;
        }

        $this->recordEvent(new PaymentWasRefunded(
            paymentId: $this->id->toString(),
            refundAmount: $refundAmount->amount(),
        ));
    }

    /**
     * Для двухстадийных платежей с частичным capture — рефандим только captured сумму.
     * Для прямых платежей (capturedAmountKopecks = 0) — полная сумма.
     */
    private function refundableBase(): int
    {
        return $this->capturedAmountKopecks > 0
            ? $this->capturedAmountKopecks
            : $this->amount->amount();
    }

    public function refundedAmountKopecks(): int
    {
        return $this->refundedAmountKopecks;
    }

    public function capturedAmountKopecks(): int
    {
        return $this->capturedAmountKopecks;
    }

    public function assignExternalData(ExternalId $externalId, string $confirmationUrl, ?string $paymentMethodId = null): void
    {
        $this->externalId = $externalId;
        $this->confirmationUrl = $confirmationUrl;
        $this->paymentMethodId = $paymentMethodId;
    }

    public function requireThreeDSecure(string $challengeUrl): void
    {
        $this->threeDsRequired = true;
        $this->threeDsChallengeUrl = $challengeUrl;

        $this->recordEvent(new PaymentRequiresThreeDSecure(
            paymentId: $this->id->toString(),
            challengeUrl: $challengeUrl,
            provider: $this->provider,
        ));
    }

    public function threeDsRequired(): bool
    {
        return $this->threeDsRequired;
    }

    public function threeDsChallengeUrl(): ?string
    {
        return $this->threeDsChallengeUrl;
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

    /** @return array<string, mixed> */
    public function metadata(): array
    {
        return $this->metadata;
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @param  SplitRule[]  $splits
     */
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
        int $capturedAmountKopecks = 0,
        bool $threeDsRequired = false,
        ?string $threeDsChallengeUrl = null,
        array $splits = [],
    ): self {
        $payment = new self(
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
            capturedAmountKopecks: $capturedAmountKopecks,
            threeDsRequired: $threeDsRequired,
            threeDsChallengeUrl: $threeDsChallengeUrl,
        );

        $payment->splits = $splits;

        return $payment;
    }
}
