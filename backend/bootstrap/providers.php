<?php

declare(strict_types=1);

use App\Providers\AppServiceProvider;
use App\Providers\CryptoServiceProvider;
use App\Providers\PaymentServiceProvider;

return [
    AppServiceProvider::class,
    PaymentServiceProvider::class,
    CryptoServiceProvider::class,
];
