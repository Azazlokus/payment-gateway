<?php

declare(strict_types=1);

namespace App\CryptoPayments\Domain\Enums;

enum CryptoRefundStatus: string
{
    case Pending = 'pending';
    case Broadcasting = 'broadcasting';
    case Completed = 'completed';
    case Failed = 'failed';

    public function isTerminal(): bool
    {
        return match ($this) {
            self::Completed, self::Failed => true,
            default => false,
        };
    }
}
