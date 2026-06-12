<?php

declare(strict_types=1);

namespace App\Contexts\Payments\Domain\Enums;

enum PaymentMethodType: string
{
    case Card = 'card';
    case Sbp = 'sbp';
    case YooMoney = 'yoo_money';
    case BankAccount = 'bank_account';
}
