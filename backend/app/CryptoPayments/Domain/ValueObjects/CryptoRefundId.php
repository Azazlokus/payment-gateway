<?php

declare(strict_types=1);

namespace App\CryptoPayments\Domain\ValueObjects;

use Symfony\Component\Uid\Ulid;

final readonly class CryptoRefundId
{
    private function __construct(private string $value) {}

    public static function generate(): self
    {
        return new self((string) new Ulid);
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
