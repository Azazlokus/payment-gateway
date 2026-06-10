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
     * Cursor-based pagination — O(log n) regardless of depth, unlike offset.
     * Cursor is an opaque base64 string returned by the previous response.
     *
     * @param  array<string, mixed>  $filters
     * @return array{data: Payment[], per_page: int, next_cursor: string|null, prev_cursor: string|null}
     */
    public function cursorPaginate(int $perPage, ?string $cursor, array $filters): array;

    /**
     * Returns a lazy cursor over all payments matching filters, for streaming export.
     *
     * @param  array<string, mixed>  $filters
     * @return iterable<Payment>
     */
    public function cursor(array $filters): iterable;
}
