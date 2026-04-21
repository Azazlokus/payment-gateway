<?php

declare(strict_types=1);

namespace App\Payments\Domain\Contracts;

use App\Payments\Domain\Aggregates\Payment;
use App\Payments\Domain\ValueObjects\PaymentId;

interface PaymentRepositoryInterface
{
    public function save(Payment $payment): void;

    public function findById(PaymentId $id): ?Payment;

    public function findByIdempotencyKey(string $key): ?Payment;

    public function findByExternalId(string $externalId): ?Payment;

    /**
     * @param  array<string, mixed> $filters
     * @return array{data: Payment[], total: int, per_page: int, current_page: int, last_page: int}
     */
    public function paginate(int $perPage, int $page, array $filters): array;

    /**
     * Returns a lazy cursor over all payments matching filters, for streaming export.
     *
     * @param  array<string, mixed> $filters
     * @return iterable<Payment>
     */
    public function cursor(array $filters): iterable;
}
