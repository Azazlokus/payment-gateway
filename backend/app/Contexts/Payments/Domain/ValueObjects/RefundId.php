<?php

declare(strict_types=1);

namespace App\Contexts\Payments\Domain\ValueObjects;

use InvalidArgumentException;
use Symfony\Component\Uid\Ulid;

final readonly class RefundId implements \Stringable
{
    private function __construct(private string $value) {}

    public static function generate(): self
    {
        return new self((string) new Ulid);
    }

    public static function fromString(string $value): self
    {
        if (in_array(trim($value), ['', '0'], true)) {
            throw new InvalidArgumentException('RefundId cannot be empty');
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
