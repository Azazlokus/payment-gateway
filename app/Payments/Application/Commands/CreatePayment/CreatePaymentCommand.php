<?php

declare(strict_types=1);

namespace App\Payments\Application\Commands\CreatePayment;

use App\Payments\Application\DTOs\CreatePaymentOptionsDTO;

final readonly class CreatePaymentCommand
{
    public function __construct(
        public int $amountKopecks,
        public string $description,
        public string $returnUrl,
        public string $idempotencyKey,
        public ?int $userId = null,
        public array $metadata = [],
        public CreatePaymentOptionsDTO $options = new CreatePaymentOptionsDTO,
    ) {}
}
