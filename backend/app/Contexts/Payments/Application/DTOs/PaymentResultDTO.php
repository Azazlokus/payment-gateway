<?php

declare(strict_types=1);

namespace App\Contexts\Payments\Application\DTOs;

use App\Contexts\Payments\Domain\Aggregates\Payment;
use App\Contexts\Payments\Domain\ValueObjects\SplitRule;

final readonly class PaymentResultDTO
{
    public function __construct(
        public string $paymentId,
        public string $status,
        public int $amount,
        public string $currency,
        public ?string $confirmationUrl,
        public ?string $externalId,
        public ?string $paymentMethodId = null,
        public int $refundedAmount = 0,
        public int $capturedAmount = 0,
        public bool $threeDsRequired = false,
        public ?string $threeDsChallengeUrl = null,
        /** @var array<array{account_id: string, amount: int, currency: string, description: string}> */
        public array $splits = [],
    ) {}

    public static function fromAggregate(Payment $payment): self
    {
        return new self(
            paymentId: $payment->id()->toString(),
            status: $payment->status()->value,
            amount: $payment->amount()->amount(),
            currency: $payment->amount()->currency()->value,
            confirmationUrl: $payment->confirmationUrl(),
            externalId: $payment->externalId()?->toString(),
            paymentMethodId: $payment->paymentMethodId(),
            refundedAmount: $payment->refundedAmountKopecks(),
            capturedAmount: $payment->capturedAmountKopecks(),
            threeDsRequired: $payment->threeDsRequired(),
            threeDsChallengeUrl: $payment->threeDsChallengeUrl(),
            splits: array_map(
                fn (SplitRule $s) => $s->toArray(),
                $payment->splits(),
            ),
        );
    }
}
