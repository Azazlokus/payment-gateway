<?php

use Illuminate\Support\Facades\Route;

// SPA — все маршруты кроме /api/* отдаём Vue Router
Route::get('/{any?}', fn () => view('spa'))
    ->where('any', '^(?!api).*$')
    ->name('spa');
