<?php

declare(strict_types=1);

namespace App\Contexts\CryptoPayments\Domain\Contracts;

use App\Contexts\CryptoPayments\Domain\Aggregates\CryptoRefundRequest;
use App\Contexts\CryptoPayments\Domain\ValueObjects\CryptoRefundId;

interface CryptoRefundRepositoryInterface
{
    public function save(CryptoRefundRequest $refund): void;

    public function findById(CryptoRefundId $id): ?CryptoRefundRequest;

    public function existsForDeposit(string $depositId): bool;

    /** @return list<CryptoRefundRequest> */
    public function findPending(): array;
}
