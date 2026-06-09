<?php

declare(strict_types=1);

namespace App\Payments\Domain\ValueObjects;

use App\Payments\Domain\Exceptions\PaymentException;

final readonly class SplitRule
{
    public function __construct(
        private string $accountId,
        private Money $amount,
        private string $description = '',
    ) {
        if (trim($accountId) === '') {
            throw new PaymentException('Split rule account ID cannot be empty');
        }
    }

    public function accountId(): string
    {
        return $this->accountId;
    }

    public function amount(): Money
    {
        return $this->amount;
    }

    public function description(): string
    {
        return $this->description;
    }

    /** @return array{account_id: string, amount: int, currency: string, description: string} */
    public function toArray(): array
    {
        return [
            'account_id' => $this->accountId,
            'amount' => $this->amount->amount(),
            'currency' => $this->amount->currency()->value,
            'description' => $this->description,
        ];
    }
}
