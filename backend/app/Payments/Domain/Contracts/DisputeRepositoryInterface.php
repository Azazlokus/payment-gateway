<?php

declare(strict_types=1);

namespace App\Payments\Domain\Contracts;

use App\Payments\Domain\Aggregates\Dispute;
use App\Payments\Domain\ValueObjects\DisputeId;
use App\Payments\Domain\ValueObjects\PaymentId;

interface DisputeRepositoryInterface
{
    public function save(Dispute $dispute): void;

    public function findById(DisputeId $id): ?Dispute;

    /** @return Dispute[] */
    public function findByPaymentId(PaymentId $paymentId): array;
}
