<?php

declare(strict_types=1);

namespace App\Contexts\Payments\Domain\Contracts;

use App\Contexts\Payments\Domain\ValueObjects\Money;

interface SupportsTokenization
{
    public function tokenize(string $paymentId): TokenizationResult;

    public function chargeToken(
        string $token,
        Money $amount,
        string $description,
        string $idempotencyKey,
    ): ProviderResponse;

    public function deleteToken(string $token): void;
}
