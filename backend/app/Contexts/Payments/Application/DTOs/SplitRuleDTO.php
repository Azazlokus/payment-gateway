<?php

declare(strict_types=1);

namespace App\Contexts\Payments\Application\DTOs;

final readonly class SplitRuleDTO
{
    public function __construct(
        public string $accountId,
        public int $amountKopecks,
        public string $description = '',
    ) {}
}
