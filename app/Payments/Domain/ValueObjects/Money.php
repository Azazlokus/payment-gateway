<?php

declare(strict_types=1);

namespace App\Payments\Domain\ValueObjects;

use App\Payments\Domain\Enums\Currency;
use App\Payments\Domain\Exceptions\PaymentException;

final readonly class Money
{
    public function __construct(
        private int      $amount,  // в копейках — никакого float
        private Currency $currency,
    )
    {
        if ($amount < 0) {
            throw new PaymentException('Money amount cannot be negative');
        }

        if ($amount < 100) {
            throw new PaymentException('Minimum payment amount is 1 RUB (100 kopecks)');
        }
    }

    public static function ofRub(int $kopecks): self
    {
        return new self($kopecks, Currency::RUB);
    }

    public function amount(): int
    {
        return $this->amount;
    }

    public function currency(): Currency
    {
        return $this->currency;
    }

    public function formatted(): string
    {
        return number_format($this->amount / 100, 2, '.', '') . ' ' . $this->currency->value;
    }

    public function equals(self $other): bool
    {
        return $this->amount === $other->amount
            && $this->currency === $other->currency;
    }

    public function isGreaterThan(self $other): bool
    {
        $this->guardSameCurrency($other);
        return $this->amount > $other->amount;
    }

    private function guardSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new PaymentException('Cannot compare money of different currencies');
        }
    }
}
