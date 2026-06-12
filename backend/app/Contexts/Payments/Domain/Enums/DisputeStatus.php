<?php

declare(strict_types=1);

namespace App\Contexts\Payments\Domain\Enums;

enum DisputeStatus: string
{
    case Filed = 'Filed';
    case Won = 'Won';
    case Lost = 'Lost';

    public function isResolved(): bool
    {
        return match ($this) {
            self::Won, self::Lost => true,
            self::Filed => false,
        };
    }
}
