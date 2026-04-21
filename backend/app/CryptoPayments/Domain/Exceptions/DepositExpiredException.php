<?php

declare(strict_types=1);

namespace App\CryptoPayments\Domain\Exceptions;

final class DepositExpiredException extends CryptoDepositException
{
    public function __construct(string $id)
    {
        parent::__construct("Deposit {$id} has expired", 422);
    }
}
