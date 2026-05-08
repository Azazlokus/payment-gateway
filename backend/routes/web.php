<?php

use App\PaymentLinks\Http\Controllers\PaymentLinkController;
use Illuminate\Support\Facades\Route;

// ─── Payment Links (публичные страницы оплаты) ────────────────────────────────
Route::get('/pay/{token}',          [PaymentLinkController::class, 'show'])->name('pay.show');
Route::post('/pay/{token}',         [PaymentLinkController::class, 'pay'])->name('pay.submit');
Route::get('/pay/{token}/success',  [PaymentLinkController::class, 'success'])->name('pay.success');

// SPA — все маршруты кроме /api/* и /pay/* отдаём Vue Router
Route::get('/{any?}', fn () => view('spa'))
    ->where('any', '^(?!api|pay).*$')
    ->name('spa');
