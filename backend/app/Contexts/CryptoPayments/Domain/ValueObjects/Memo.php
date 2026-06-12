<?php

declare(strict_types=1);

namespace App\Contexts\CryptoPayments\Domain\ValueObjects;

use InvalidArgumentException;

final readonly class Memo implements \Stringable
{
    private function __construct(private string $value) {}

    public static function generate(): self
    {
        return new self((string) random_int(100_000_000, 999_999_999));
    }

    public static function fromString(string $value): self
    {
        if (! preg_match('/^\d{1,10}$/', $value)) {
            throw new InvalidArgumentException("Invalid Memo: must be digits only, max 10 chars. Got: {$value}");
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
}
