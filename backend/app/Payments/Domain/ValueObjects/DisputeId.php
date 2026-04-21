<?php

declare(strict_types=1);

namespace App\Payments\Domain\ValueObjects;

use InvalidArgumentException;
use Symfony\Component\Uid\Ulid;

final readonly class DisputeId
{
    private function __construct(private string $value) {}

    public static function generate(): self
    {
        return new self((string) new Ulid);
    }

    public static function fromString(string $value): self
    {
        if (! Ulid::isValid($value)) {
            throw new InvalidArgumentException("Invalid DisputeId: {$value}");
        }

        return new self($value);
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
