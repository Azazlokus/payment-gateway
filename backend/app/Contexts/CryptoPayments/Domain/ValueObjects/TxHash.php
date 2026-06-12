<?php

declare(strict_types=1);

namespace App\Contexts\CryptoPayments\Domain\ValueObjects;

use InvalidArgumentException;

final readonly class TxHash
{
    private function __construct(private string $value) {}

    public static function fromString(string $value): self
    {
        if (trim($value) === '') {
            throw new InvalidArgumentException('TxHash cannot be empty');
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
