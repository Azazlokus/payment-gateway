<?php

declare(strict_types=1);

namespace App\CryptoPayments\Domain\Enums;

enum CryptoDepositStatus: string
{
    case Awaiting = 'Awaiting';
    case Detected = 'Detected';
    case Confirmed = 'Confirmed';
    case Expired = 'Expired';
    case Overpaid = 'Overpaid';

    public function isTerminal(): bool
    {
        return match ($this) {
            self::Confirmed, self::Expired, self::Overpaid => true,
            default => false,
        };
    }
}
