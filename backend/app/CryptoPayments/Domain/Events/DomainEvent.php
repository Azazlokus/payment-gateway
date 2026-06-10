<?php

declare(strict_types=1);

namespace App\CryptoPayments\Domain\Events;

use DateTimeImmutable;
use DateTimeInterface;
use Symfony\Component\Uid\Uuid;

abstract readonly class DomainEvent
{
    public string $occurredAt;

    public string $eventId;

    public function __construct()
    {
        $this->occurredAt = (new DateTimeImmutable)->format(DateTimeInterface::ATOM);
        $this->eventId = Uuid::v4()->toRfc4122();
    }

    abstract public function eventName(): string;

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
