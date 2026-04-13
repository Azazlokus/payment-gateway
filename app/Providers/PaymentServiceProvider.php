<?php

declare(strict_types=1);

namespace App\Providers;

use App\Payments\Domain\Contracts\PaymentProviderInterface;
use App\Payments\Domain\Contracts\PaymentRepositoryInterface;
use App\Payments\Infrastructure\Observability\CorrelationIdMiddleware;
use App\Payments\Infrastructure\Observability\PaymentLogger;
use App\Payments\Infrastructure\Persistence\EloquentPaymentRepository;
use App\Payments\Infrastructure\Providers\AlfaBankProvider;
use App\Payments\Infrastructure\Providers\RobokassaProvider;
use App\Payments\Infrastructure\Providers\SbpProvider;
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

        $this->app->singleton(RobokassaProvider::class, function () {
            return new RobokassaProvider(
                login:     config('payments.robokassa.login'),
                password1: config('payments.robokassa.password1'),
                password2: config('payments.robokassa.password2'),
                isTest:    (bool) config('payments.robokassa.is_test', true),
                logger:    app(PaymentLogger::class),
            );
        });

        $this->app->singleton(SbpProvider::class, function () {
            return new SbpProvider(
                merchantId:     config('payments.sbp.merchant_id'),
                apiKey:         config('payments.sbp.api_key'),
                webhookSecret:  config('payments.sbp.webhook_secret'),
                baseUrl:        config('payments.sbp.base_url'),
                logger:         app(PaymentLogger::class),
            );
        });

        $this->app->singleton(AlfaBankProvider::class, function () {
            return new AlfaBankProvider(
                login:    config('payments.alfabank.login'),
                password: config('payments.alfabank.password'),
                baseUrl:  config('payments.alfabank.base_url'),
                logger:   app(PaymentLogger::class),
            );
        });

        $this->app->singleton(
            PaymentProviderInterface::class,
            function () {
                return match (config('payments.default')) {
                    'yookassa' => new YooKassaProvider(
                        shopId:    config('payments.yookassa.shop_id'),
                        secretKey: config('payments.yookassa.secret_key'),
                        logger:    app(PaymentLogger::class),
                    ),
                    'robokassa' => app(RobokassaProvider::class),
                    'sbp'       => app(SbpProvider::class),
                    'alfabank'  => app(AlfaBankProvider::class),
                    default => throw new \InvalidArgumentException(
                        'Unknown payment provider: ' . config('payments.default')
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
