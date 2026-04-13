<?php

declare(strict_types=1);

namespace App\Payments\Domain\Contracts;

use App\Payments\Domain\ValueObjects\ExternalId;

final readonly class ProviderResponse
{
    public function __construct(
        public ExternalId $externalId,
        public string $confirmationUrl,
        public string $status,
        public ?string $paymentMethodId = null, // ID сохранённого метода (для recurring)
        public ?int $refundAmountKopecks = null, // Заполняется для refund.* событий
        public array $rawData = [],
    ) {}
}
