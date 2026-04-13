<?php

declare(strict_types=1);

namespace App\Payments\Domain\ValueObjects;

use Illuminate\Support\Str;
use InvalidArgumentException;

final readonly class AttemptId
{
    private function __construct(private string $value)
    {
    }

    public static function generate(): self
    {
        return new self((string) Str::ulid());
    }

    public static function fromString(string $value): self
    {
        if (empty(trim($value))) {
            throw new InvalidArgumentException('AttemptId cannot be empty');
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
