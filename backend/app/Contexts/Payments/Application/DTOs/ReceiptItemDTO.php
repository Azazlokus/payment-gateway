<?php

declare(strict_types=1);

namespace App\Contexts\Payments\Application\DTOs;

final readonly class ReceiptItemDTO
{
    public function __construct(
        public string $description,
        public float $quantity,
        public int $amountKopecks,   // сумма за единицу
        public int $vatCode,         // 1=без НДС, 2=0%, 3=10%, 4=20%
        public string $paymentSubject = 'commodity',  // commodity|service|...
        public string $paymentMode = 'full_payment',
    ) {}
}
