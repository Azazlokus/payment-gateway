<?php

declare(strict_types=1);

namespace App\Contexts\CryptoPayments\Domain\Exceptions;

final class DepositAlreadyConfirmedException extends CryptoDepositException
{
    public function __construct(string $id)
    {
        parent::__construct("Deposit {$id} is already confirmed", 409);
    }
}
