<?php

declare(strict_types=1);

namespace App\Payments\Domain\ValueObjects;

use InvalidArgumentException;

final readonly class ExternalId
{
    private function __construct(private string $value) {}

    public static function fromString(string $value): self
    {
        if (empty(trim($value))) {
            throw new InvalidArgumentException('ExternalId cannot be empty');
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
