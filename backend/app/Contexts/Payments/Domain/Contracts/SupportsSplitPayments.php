<?php

declare(strict_types=1);

namespace App\Contexts\Payments\Domain\Contracts;

use App\Contexts\Payments\Application\DTOs\CreatePaymentOptionsDTO;
use App\Contexts\Payments\Domain\ValueObjects\Money;
use App\Contexts\Payments\Domain\ValueObjects\SplitRule;

interface SupportsSplitPayments
{
    /**
     * @param  SplitRule[]  $splits
     */
    public function createSplitPayment(
        string $paymentId,
        Money $amount,
        string $description,
        string $returnUrl,
        string $idempotencyKey,
        array $splits,
        CreatePaymentOptionsDTO $options = new CreatePaymentOptionsDTO,
    ): ProviderResponse;
}
