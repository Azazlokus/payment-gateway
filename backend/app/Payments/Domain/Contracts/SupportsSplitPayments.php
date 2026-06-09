<?php

declare(strict_types=1);

namespace App\Payments\Domain\Contracts;

use App\Payments\Application\DTOs\CreatePaymentOptionsDTO;
use App\Payments\Domain\ValueObjects\Money;
use App\Payments\Domain\ValueObjects\SplitRule;

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
