<?php

declare(strict_types=1);

namespace App\CryptoPayments\Domain\ValueObjects;

use InvalidArgumentException;

final readonly class TonAddress
{
    private function __construct(private string $value) {}

    public static function fromString(string $value): self
    {
        if (! preg_match('/^(0:[0-9a-fA-F]{64}|[A-Za-z0-9_-]{48})$/', $value)) {
            throw new InvalidArgumentException("Invalid TON address: {$value}");
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

    /**
     * Returns the non-bounceable version of this address.
     * For user-friendly addresses starting with "EQ", replaces prefix with "UQ".
     * Non-bounceable addresses should always be used when receiving payments.
     */
    public function toNonBounceable(): self
    {
        // Raw format (0:hex) stays as-is — not a user-friendly format
        if (str_starts_with($this->value, '0:')) {
            return new self($this->value);
        }

        // Already non-bounceable (UQ prefix)
        if (str_starts_with($this->value, 'UQ')) {
            return new self($this->value);
        }

        // Bounceable (EQ prefix) → non-bounceable (UQ prefix)
        if (str_starts_with($this->value, 'EQ')) {
            return new self('UQ' . substr($this->value, 2));
        }

        // Other formats — return as-is
        return new self($this->value);
    }
}
