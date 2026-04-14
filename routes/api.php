<?php

declare(strict_types=1);

use App\Payments\Presentation\Http\Controllers\HealthController;
use App\Payments\Presentation\Http\Controllers\PaymentController;
use App\Payments\Presentation\Http\Controllers\AlfaBankWebhookController;
use App\Payments\Presentation\Http\Controllers\CloudPaymentsWebhookController;
use App\Payments\Presentation\Http\Controllers\MetricsController;
use App\Payments\Presentation\Http\Controllers\RobokassaWebhookController;
use App\Payments\Presentation\Http\Controllers\SbpWebhookController;
use App\Payments\Presentation\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

/*
 * Payments API
 * Rate limit: 30 запросов/мин на создание, 60 на чтение
 */
/*
 * Health check — без middleware, без throttle
 */
Route::get('/health', [HealthController::class, 'check'])->name('health');

Route::middleware(['correlation'])->group(function () {

    // Список платежей
    Route::get('/payments', [PaymentController::class, 'index'])
        ->middleware('throttle:60,1');

    // Создание платежа — строгий лимит для защиты от спама
    Route::post('/payments', [PaymentController::class, 'create'])
        ->middleware('throttle:30,1');

    // Чтение и мутации конкретного платежа
    Route::prefix('payments/{id}')->group(function () {
        Route::get('/', [PaymentController::class, 'show'])
            ->middleware('throttle:60,1');

        Route::post('/cancel', [PaymentController::class, 'cancel'])
            ->middleware('throttle:30,1');

        Route::post('/refund', [PaymentController::class, 'refund'])
            ->middleware('throttle:30,1');

        Route::post('/sync', [PaymentController::class, 'sync'])
            ->middleware('throttle:30,1');
    });
});

/*
 * Webhook от YooKassa
 * Без correlation middleware, без throttle (YooKassa сама управляет ретраями)
 * IP-фильтрация — внутри WebhookController через YooKassaProvider::verifyWebhook()
 */
Route::post('/webhook/yookassa', [WebhookController::class, 'yookassa'])
    ->name('webhook.yookassa');

/*
 * Webhook от Robokassa (ResultURL)
 * Принимает form POST. IP-фильтрация — внутри RobokassaProvider::verifyWebhook().
 * Ответ — plain text "OK{InvId}", иначе Robokassa будет повторять запросы.
 */
Route::post('/webhook/robokassa', [RobokassaWebhookController::class, 'handle'])
    ->name('webhook.robokassa');

/*
 * Webhook от СБП (банк-эквайер)
 * JSON POST. Верификация — заголовок X-Api-Key.
 */
Route::post('/webhook/sbp', [SbpWebhookController::class, 'handle'])
    ->name('webhook.sbp');

/*
 * Webhook от Альфа-Банка
 * Form POST. Верификация — наличие обязательных полей (mdOrder, operation).
 */
Route::post('/webhook/alfabank', [AlfaBankWebhookController::class, 'handle'])
    ->name('webhook.alfabank');

/*
 * Webhook от CloudPayments
 * JSON POST. Верификация — Content-HMAC (HMAC-SHA256 тела запроса).
 * Ответ — JSON {code: 0} (успех) или {code: 13} (отклонение).
 */
Route::post('/webhook/cloudpayments', [CloudPaymentsWebhookController::class, 'handle'])
    ->name('webhook.cloudpayments');

/*
 * Prometheus-метрики для Grafana/Alertmanager
 * Throttle: 60 запросов/мин. В production дополнительно защитить через
 * IP-whitelist или bearer token на уровне nginx/ingress.
 */
Route::get('/metrics', MetricsController::class)
    ->middleware('throttle:60,1')
    ->name('metrics');
