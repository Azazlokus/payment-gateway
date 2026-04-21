<?php

declare(strict_types=1);

namespace App\Payments\Domain\Enums;

enum PaymentStatus: string
{
    case Pending = 'Pending';
    case Succeeded = 'Succeeded';
    case Cancelled = 'Cancelled';
    case Refunded = 'Refunded';

    public function isTerminal(): bool
    {
        return match ($this) {
            self::Succeeded, self::Cancelled, self::Refunded => true,
            self::Pending => false,
        };
    }
}
