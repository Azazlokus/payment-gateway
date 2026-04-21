<?php

declare(strict_types=1);

namespace App\CryptoPayments\Domain\ValueObjects;

use InvalidArgumentException;

final readonly class CryptoAddress
{
    private function __construct(private string $value) {}

    public static function fromString(string $value): self
    {
        if (trim($value) === '') {
            throw new InvalidArgumentException('CryptoAddress cannot be empty.');
        }

        return new self(trim($value));
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
