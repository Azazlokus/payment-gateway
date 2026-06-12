<?php

declare(strict_types=1);

use App\Contexts\CryptoPayments\Presentation\Http\Controllers\CryptoDepositController;
use App\Contexts\Payments\Presentation\Http\Controllers\AlfaBankWebhookController;
use App\Contexts\Payments\Presentation\Http\Controllers\AnalyticsController;
use App\Contexts\Payments\Presentation\Http\Controllers\AuditLogController;
use App\Contexts\Payments\Presentation\Http\Controllers\CloudPaymentsWebhookController;
use App\Contexts\Payments\Presentation\Http\Controllers\DisputeController;
use App\Contexts\Payments\Presentation\Http\Controllers\HealthController;
use App\Contexts\Payments\Presentation\Http\Controllers\InvoiceController;
use App\Contexts\Payments\Presentation\Http\Controllers\MetricsController;
use App\Contexts\Payments\Presentation\Http\Controllers\PaymentController;
use App\Contexts\Payments\Presentation\Http\Controllers\PaymentMethodController;
use App\Contexts\Payments\Presentation\Http\Controllers\PaymentStatusStreamController;
use App\Contexts\Payments\Presentation\Http\Controllers\RecurringController;
use App\Contexts\Payments\Presentation\Http\Controllers\RefundHistoryController;
use App\Contexts\Payments\Presentation\Http\Controllers\RobokassaWebhookController;
use App\Contexts\Payments\Presentation\Http\Controllers\SbpWebhookController;
use App\Contexts\Payments\Presentation\Http\Controllers\WebhookController;
use App\Contexts\Payments\Presentation\Http\Controllers\WebhookLogController;
use App\PaymentLinks\Http\Controllers\PaymentLinkController;
use Illuminate\Support\Facades\Route;

// ─── Unversioned infrastructure endpoints ─────────────────────────────────────
//
// /health   — liveness probe; load balancers hit this, no versioning needed
// /metrics  — Prometheus scraper uses a fixed URL; versioning would break dashboards
// /webhook/* — providers have hardcoded callback URLs; cannot change without
//              re-registering in every provider's control panel

Route::get('/health', [HealthController::class, 'check'])->name('health');

Route::get('/metrics', MetricsController::class)
    ->middleware('throttle:60,1')
    ->name('metrics');

Route::post('/webhook/yookassa', [WebhookController::class, 'yookassa'])
    ->middleware('throttle:webhook.yookassa')
    ->name('webhook.yookassa');

Route::post('/webhook/robokassa', [RobokassaWebhookController::class, 'handle'])
    ->middleware('throttle:webhook.robokassa')
    ->name('webhook.robokassa');

Route::post('/webhook/sbp', [SbpWebhookController::class, 'handle'])
    ->middleware('throttle:webhook.sbp')
    ->name('webhook.sbp');

Route::post('/webhook/alfabank', [AlfaBankWebhookController::class, 'handle'])
    ->middleware('throttle:webhook.alfabank')
    ->name('webhook.alfabank');

Route::post('/webhook/cloudpayments', [CloudPaymentsWebhookController::class, 'handle'])
    ->middleware('throttle:webhook.cloudpayments')
    ->name('webhook.cloudpayments');

// ─── API v1 ───────────────────────────────────────────────────────────────────

Route::prefix('v1')->name('v1.')->middleware(['correlation', 'auth.api', 'resolve.tenant'])->group(function () {

    // ── Payments ────────────────────────────────────────────────────────────

    Route::get('/payments', [PaymentController::class, 'index'])
        ->middleware('throttle:60,1')
        ->name('payments.index');

    Route::get('/payments/export', [PaymentController::class, 'export'])
        ->middleware('throttle:10,1')
        ->name('payments.export');

    Route::post('/payments', [PaymentController::class, 'create'])
        ->middleware('throttle:30,1')
        ->name('payments.create');

    Route::prefix('payments/{id}')->name('payments.')->group(function () {
        Route::get('/', [PaymentController::class, 'show'])
            ->middleware('throttle:60,1')
            ->name('show');

        Route::post('/cancel', [PaymentController::class, 'cancel'])
            ->middleware('throttle:30,1')
            ->name('cancel');

        Route::post('/capture', [PaymentController::class, 'capture'])
            ->middleware('throttle:30,1')
            ->name('capture');

        Route::post('/refund', [PaymentController::class, 'refund'])
            ->middleware('throttle:30,1')
            ->name('refund');

        Route::post('/retry', [PaymentController::class, 'retry'])
            ->middleware('throttle:10,1')
            ->name('retry');

        Route::post('/sync', [PaymentController::class, 'sync'])
            ->middleware('throttle:30,1')
            ->name('sync');

        Route::post('/resync', [PaymentController::class, 'resync'])
            ->middleware('throttle:10,1')
            ->name('resync');

        // SSE — real-time статус платежа (EventSource)
        Route::get('/stream', PaymentStatusStreamController::class)
            ->middleware('throttle:10,1')
            ->name('stream');

        // PDF квитанция
        Route::get('/invoice', InvoiceController::class)
            ->middleware('throttle:20,1')
            ->name('invoice');

        // Лог исходящих уведомлений по платежу
        Route::get('/webhook-logs', [WebhookLogController::class, 'forPayment'])
            ->middleware('throttle:60,1')
            ->name('webhook-logs');

        // История возвратов по платежу
        Route::get('/refunds', RefundHistoryController::class)
            ->middleware('throttle:60,1')
            ->name('refunds');

        Route::get('/disputes', [DisputeController::class, 'index'])
            ->middleware('throttle:60,1')
            ->name('disputes.index');

        Route::post('/disputes', [DisputeController::class, 'store'])
            ->middleware('throttle:10,1')
            ->name('disputes.store');
    });

    // ── Payment Methods (Tokenization) ────────────────────────────────────

    Route::get('/payment-methods', [PaymentMethodController::class, 'index'])
        ->middleware('throttle:60,1')
        ->name('payment-methods.index');

    Route::post('/payment-methods', [PaymentMethodController::class, 'store'])
        ->middleware('throttle:30,1')
        ->name('payment-methods.store');

    Route::post('/payment-methods/{id}/charge', [PaymentMethodController::class, 'charge'])
        ->middleware('throttle:30,1')
        ->name('payment-methods.charge');

    Route::delete('/payment-methods/{id}', [PaymentMethodController::class, 'destroy'])
        ->middleware('throttle:30,1')
        ->name('payment-methods.destroy');

    // ── Disputes ────────────────────────────────────────────────────────────

    Route::prefix('disputes/{id}')->name('disputes.')->group(function () {
        Route::get('/', [DisputeController::class, 'show'])
            ->middleware('throttle:60,1')
            ->name('show');

        Route::post('/resolve', [DisputeController::class, 'resolve'])
            ->middleware('throttle:10,1')
            ->name('resolve');
    });

    // ── Recurring payments ──────────────────────────────────────────────────

    Route::get('/recurring/methods', [RecurringController::class, 'methods'])->middleware('throttle:60,1')->name('recurring.methods');
    Route::post('/recurring/charge', [RecurringController::class, 'charge'])->middleware('throttle:30,1')->name('recurring.charge');

    // ── Webhook Logs (общий список) ─────────────────────────────────────────

    Route::get('/webhook-logs', [WebhookLogController::class, 'index'])
        ->middleware('throttle:60,1')
        ->name('webhook-logs.index');

    // ── Analytics ──────────────────────────────────────────────────────────

    Route::prefix('analytics')->name('analytics.')->group(function () {
        Route::get('/revenue', [AnalyticsController::class, 'revenue'])
            ->middleware('throttle:60,1')
            ->name('revenue');
        Route::get('/funnel', [AnalyticsController::class, 'funnel'])
            ->middleware('throttle:60,1')
            ->name('funnel');
    });

    // ── Audit Logs ─────────────────────────────────────────────────────────

    Route::get('/audit-logs', [AuditLogController::class, 'index'])
        ->middleware('throttle:60,1')
        ->name('audit-logs.index');

    // ── Payment Links ───────────────────────────────────────────────────────

    Route::get('/payment-links', [PaymentLinkController::class, 'index'])->middleware('throttle:60,1')->name('payment-links.index');
    Route::post('/payment-links', [PaymentLinkController::class, 'store'])->middleware('throttle:30,1')->name('payment-links.store');
    Route::delete('/payment-links/{id}', [PaymentLinkController::class, 'destroy'])->middleware('throttle:30,1')->name('payment-links.destroy');

    // ── Crypto deposits (TON / USDT-TON) ────────────────────────────────────

    Route::prefix('crypto')->name('crypto.')->group(function () {
        Route::post('/deposits', [CryptoDepositController::class, 'store'])
            ->middleware('throttle:30,1')
            ->name('deposits.store');

        Route::post('/deposits/{id}/refund', [CryptoDepositController::class, 'refund'])
            ->middleware('throttle:10,1')
            ->name('deposits.refund');

        Route::get('/deposits/{id}', [CryptoDepositController::class, 'show'])
            ->middleware('throttle:60,1')
            ->name('deposits.show');
    });
});
