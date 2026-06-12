<?php

declare(strict_types=1);

namespace App\Contexts\Payments\Application\Commands\ChargeToken;

final readonly class ChargeTokenCommand
{
    public function __construct(
        public string $paymentMethodId,
        public int $amountKopecks,
        public string $description,
        public string $returnUrl,
        public string $idempotencyKey,
        public ?int $userId = null,
        /** @var array<string, mixed> */
        public array $metadata = [],
    ) {}
}
