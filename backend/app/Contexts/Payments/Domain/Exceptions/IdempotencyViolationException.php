<?php

declare(strict_types=1);

namespace App\Contexts\Payments\Domain\Exceptions;

final class IdempotencyViolationException extends PaymentException
{
    public function __construct(string $key)
    {
        parent::__construct("Idempotency key conflict: '{$key}'", 409);
    }
}
