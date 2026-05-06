<?php

declare(strict_types=1);

namespace App\CryptoPayments\Domain\ValueObjects;

use Illuminate\Support\Str;

final readonly class CryptoRefundId
{
    private function __construct(public readonly string $value) {}

    public static function generate(): self
    {
        return new self(Str::ulid()->toBase32());
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function toString(): string
    {
        return $this->value;
    }
}
