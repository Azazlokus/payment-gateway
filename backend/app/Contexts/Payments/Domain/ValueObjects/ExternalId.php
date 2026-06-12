<?php

declare(strict_types=1);

namespace App\Contexts\Payments\Domain\ValueObjects;

use InvalidArgumentException;

final readonly class ExternalId implements \Stringable
{
    private function __construct(private string $value) {}

    public static function fromString(string $value): self
    {
        if (in_array(trim($value), ['', '0'], true)) {
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
