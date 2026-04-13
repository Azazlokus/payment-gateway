<?php

declare(strict_types=1);

namespace App\Providers;

use App\Payments\Domain\Contracts\PaymentProviderInterface;
use App\Payments\Domain\Contracts\PaymentRepositoryInterface;
use App\Payments\Infrastructure\Observability\CorrelationIdMiddleware;
use App\Payments\Infrastructure\Observability\PaymentLogger;
use App\Payments\Infrastructure\Persistence\EloquentPaymentRepository;
use App\Payments\Infrastructure\Providers\YooKassaProvider;
use Illuminate\Support\ServiceProvider;

class PaymentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(
            PaymentRepositoryInterface::class,
            EloquentPaymentRepository::class,
        );

        $this->app->singleton(
            PaymentProviderInterface::class,
            function () {
                return match (config('payments.default')) {
                    'yookassa' => new YooKassaProvider(
                        shopId: config('payments.yookassa.shop_id'),
                        secretKey: config('payments.yookassa.secret_key'),
                        logger: app(PaymentLogger::class),
                    ),
                    default => throw new \InvalidArgumentException(
                        'Unknown payment provider: '.config('payments.default')
                    ),
                };
            }
        );

        $this->app->singleton(PaymentLogger::class);
    }

    public function boot(): void
    {
        $this->app['router']->aliasMiddleware(
            'correlation',
            CorrelationIdMiddleware::class,
        );
    }
}
