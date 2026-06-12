<?php

declare(strict_types=1);

namespace App\Contexts\Payments\Domain\Entities;

use App\Contexts\Payments\Domain\Enums\PaymentStatus;
use App\Contexts\Payments\Domain\ValueObjects\AttemptId;
use App\Contexts\Payments\Domain\ValueObjects\ExternalId;
use App\Contexts\Payments\Domain\ValueObjects\Money;

final class PaymentAttempt
{
    private PaymentStatus $status;

    private ?ExternalId $externalId;

    private ?string $failureReason;

    private \DateTimeImmutable $createdAt;

    public function __construct(
        private readonly AttemptId $id,
        private readonly Money $amount,
        private readonly string $provider,
    ) {
        $this->status = PaymentStatus::Pending;
        $this->externalId = null;
        $this->failureReason = null;
        $this->createdAt = new \DateTimeImmutable;
    }

    public function markSucceeded(ExternalId $externalId): void
    {
        $this->status = PaymentStatus::Succeeded;
        $this->externalId = $externalId;
    }

    public function markFailed(string $reason): void
    {
        $this->status = PaymentStatus::Cancelled;
        $this->failureReason = $reason;
    }

    public function id(): AttemptId
    {
        return $this->id;
    }

    public function amount(): Money
    {
        return $this->amount;
    }

    public function provider(): string
    {
        return $this->provider;
    }

    public function status(): PaymentStatus
    {
        return $this->status;
    }

    public function externalId(): ?ExternalId
    {
        return $this->externalId;
    }

    public function failureReason(): ?string
    {
        return $this->failureReason;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function equals(self $other): bool
    {
        return $this->id->equals($other->id);
    }
}
