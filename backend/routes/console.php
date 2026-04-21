<?php

use App\CryptoPayments\Infrastructure\Jobs\ExpireCryptoDepositsJob;
use App\CryptoPayments\Infrastructure\Jobs\PollCryptoDepositsJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Очистка idempotency_key у завершённых платежей старше 90 дней
// Запускается ночью в 02:00, когда нагрузка минимальна
Schedule::command('payments:prune-idempotency-keys')->dailyAt('02:00');

// Опрос блокчейна TON на входящие транзакции (каждые 15 секунд)
Schedule::job(PollCryptoDepositsJob::class)->everyFifteenSeconds();

// Экспирация просроченных депозитов (каждую минуту)
Schedule::job(ExpireCryptoDepositsJob::class)->everyMinute();
