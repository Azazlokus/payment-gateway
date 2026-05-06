<?php

declare(strict_types=1);

namespace App\CryptoPayments\Domain\Contracts;

use App\CryptoPayments\Domain\Aggregates\CryptoRefundRequest;
use App\CryptoPayments\Domain\ValueObjects\CryptoRefundId;

interface CryptoRefundRepositoryInterface
{
    public function save(CryptoRefundRequest $refund): void;

    public function findById(CryptoRefundId $id): ?CryptoRefundRequest;

    public function existsForDeposit(string $depositId): bool;

    /** @return list<CryptoRefundRequest> */
    public function findPending(): array;
}
