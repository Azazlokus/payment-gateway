<?php

declare(strict_types=1);

namespace App\Payments\Domain\Contracts;

use App\Payments\Application\DTOs\CreatePaymentOptionsDTO;
use App\Payments\Domain\ValueObjects\ExternalId;
use App\Payments\Domain\ValueObjects\Money;

interface SupportsTwoPhasePayments
{
    public function authorizePayment(
        string $paymentId,
        Money $amount,
        string $description,
        string $returnUrl,
        string $idempotencyKey,
        CreatePaymentOptionsDTO $options = new CreatePaymentOptionsDTO,
    ): ProviderResponse;

    public function capturePayment(ExternalId $externalId, Money $amount): ProviderResponse;

    public function voidPayment(ExternalId $externalId): ProviderResponse;
}
