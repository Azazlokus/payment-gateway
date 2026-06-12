<?php

declare(strict_types=1);

namespace App\Contexts\Payments\Infrastructure\Antifraud;

final readonly class VelocityRule
{
    public function __construct(
        public string $dimension,
        public int $maxCount,
        public int $windowSeconds,
        public ?int $maxAmountKopecks = null,
    ) {}
}
