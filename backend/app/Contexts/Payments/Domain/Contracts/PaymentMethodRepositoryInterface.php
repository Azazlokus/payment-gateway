<?php

declare(strict_types=1);

namespace App\Contexts\Payments\Domain\Contracts;

use App\Contexts\Payments\Domain\Aggregates\PaymentMethod;
use App\Contexts\Payments\Domain\ValueObjects\PaymentMethodId;

interface PaymentMethodRepositoryInterface
{
    public function save(PaymentMethod $method): void;

    public function findById(PaymentMethodId $id): ?PaymentMethod;

    /** @return PaymentMethod[] */
    public function findByCustomerId(string $customerId): array;

    public function findByFingerprint(string $customerId, string $fingerprint): ?PaymentMethod;

    public function delete(PaymentMethodId $id): void;
}
