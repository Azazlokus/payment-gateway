<?php

declare(strict_types=1);

namespace App\Payments\Infrastructure\Antifraud;

use App\Payments\Domain\Exceptions\PaymentException;

class VelocityLimitExceededException extends PaymentException
{
    public function __construct(
        public readonly string $dimension,
        public readonly string $key,
        public readonly string $rule,
    ) {
        parent::__construct(
            "Velocity limit exceeded: {$rule} for {$dimension}={$key}"
        );
    }
}
