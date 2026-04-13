<?php

declare(strict_types=1);

namespace App\Payments\Domain\Contracts;

use App\Payments\Application\DTOs\CreatePaymentOptionsDTO;
use App\Payments\Domain\ValueObjects\ExternalId;
use App\Payments\Domain\ValueObjects\Money;

interface PaymentProviderInterface
{
    public function name(): string;

    public function createPayment(
        string $paymentId,
        Money $amount,
        string $description,
        string $returnUrl,
        string $idempotencyKey,
        CreatePaymentOptionsDTO $options = new CreatePaymentOptionsDTO,
    ): ProviderResponse;

    public function getPayment(ExternalId $externalId): ProviderResponse;

    public function refundPayment(ExternalId $externalId, Money $amount): ProviderResponse;

    public function verifyWebhook(array $payload, array $headers): bool;

    public function parseWebhook(array $payload): ProviderResponse;
}
