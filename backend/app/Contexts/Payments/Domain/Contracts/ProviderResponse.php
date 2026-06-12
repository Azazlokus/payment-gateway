<?php

declare(strict_types=1);

namespace App\Contexts\Payments\Domain\Contracts;

use App\Contexts\Payments\Domain\ValueObjects\ExternalId;

final readonly class ProviderResponse
{
    public function __construct(
        public ExternalId $externalId,
        public string $confirmationUrl,
        public string $status,
        public ?string $paymentMethodId = null, // ID сохранённого метода (для recurring)
        public ?int $refundAmountKopecks = null, // Заполняется для refund.* событий
        /** @var array<string, mixed> */
        public array $rawData = [],
    ) {}
}
