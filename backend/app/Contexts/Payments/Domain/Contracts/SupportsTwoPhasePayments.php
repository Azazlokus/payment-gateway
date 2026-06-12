<?php

declare(strict_types=1);

namespace App\Contexts\Payments\Domain\Contracts;

use App\Contexts\Payments\Application\DTOs\CreatePaymentOptionsDTO;
use App\Contexts\Payments\Domain\ValueObjects\ExternalId;
use App\Contexts\Payments\Domain\ValueObjects\Money;

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
