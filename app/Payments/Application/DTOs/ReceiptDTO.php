<?php

declare(strict_types=1);

namespace App\Payments\Application\DTOs;

final readonly class ReceiptDTO
{
    /**
     * @param  ReceiptItemDTO[]  $items
     */
    public function __construct(
        public array $items,
        public ?string $email = null,
        public ?string $phone = null,
    ) {}
}
