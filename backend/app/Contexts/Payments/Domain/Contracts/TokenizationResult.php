<?php

declare(strict_types=1);

namespace App\Contexts\Payments\Domain\Contracts;

final readonly class TokenizationResult
{
    public function __construct(
        public string $token,
        public string $type,
        public string $last4,
        public string $brand,
        public ?string $expiresAt = null,
    ) {}
}
