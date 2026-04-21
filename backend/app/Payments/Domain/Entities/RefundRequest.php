<?php

declare(strict_types=1);

namespace App\Payments\Domain\Entities;

use App\Payments\Domain\ValueObjects\Money;
use App\Payments\Domain\ValueObjects\RefundId;

final class RefundRequest
{
    private string $status = 'pending';

    private \DateTimeImmutable $createdAt;

    public function __construct(
        private readonly RefundId $id,
        private readonly Money $amount,
        private readonly string $reason,
    ) {
        $this->createdAt = new \DateTimeImmutable;
    }

    public function approve(): void
    {
        $this->status = 'approved';
    }

    public function reject(): void
    {
        $this->status = 'rejected';
    }

    public function id(): RefundId
    {
        return $this->id;
    }

    public function amount(): Money
    {
        return $this->amount;
    }

    public function reason(): string
    {
        return $this->reason;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
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
