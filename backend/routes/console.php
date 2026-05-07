<?php

use App\CryptoPayments\Infrastructure\Jobs\ExpireCryptoDepositsJob;
use App\CryptoPayments\Infrastructure\Jobs\PollCryptoDepositsJob;
use App\CryptoPayments\Infrastructure\Jobs\ProcessCryptoRefundsJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Очистка idempotency_key у завершённых платежей старше 90 дней
// Запускается ночью в 02:00, когда нагрузка минимальна
Schedule::command('payments:prune-idempotency-keys')->dailyAt('02:00');

// Очистка payment_method_id у завершённых платежей старше 365 дней
// Рекуррентные методы не нужны вечно; сдвинуто на 02:30 чтобы не конкурировать
Schedule::command('payments:prune-payment-methods')->dailyAt('02:30');

// Опрос блокчейна TON на входящие транзакции (каждые 15 секунд)
Schedule::job(PollCryptoDepositsJob::class)->everyFifteenSeconds();

// Экспирация просроченных депозитов (каждую минуту)
Schedule::job(ExpireCryptoDepositsJob::class)->everyMinute();

// Обработка крипто-рефандов (каждые 2 минуты)
Schedule::job(ProcessCryptoRefundsJob::class)->everyTwoMinutes();
