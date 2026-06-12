<?php

declare(strict_types=1);

namespace App\Contexts\Payments\Domain\Contracts;

use App\Contexts\Payments\Application\DTOs\CreatePaymentOptionsDTO;
use App\Contexts\Payments\Domain\ValueObjects\ExternalId;
use App\Contexts\Payments\Domain\ValueObjects\Money;

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

    /** @param array<string, mixed>            $payload
     *  @param array<string, list<string|null>> $headers */
    public function verifyWebhook(array $payload, array $headers): bool;

    /** @param array<string, mixed> $payload */
    public function parseWebhook(array $payload): ProviderResponse;
}
