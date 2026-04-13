<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Laravel\Horizon\Horizon;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Horizon::auth(function ($request) {
            return app()->environment('local')
                || in_array($request->ip(), explode(',', env('HORIZON_ALLOWED_IPS', '')));
        });
    }
}
