<?php

declare(strict_types=1);

namespace App\Providers;

use App\Payments\Application\PaymentProviderRegistry;
use App\Payments\Domain\Contracts\DisputeRepositoryInterface;
use App\Payments\Domain\Contracts\PaymentRepositoryInterface;
use App\Payments\Infrastructure\Observability\CorrelationIdMiddleware;
use App\Payments\Infrastructure\Persistence\EloquentDisputeRepository;
use App\Payments\Infrastructure\Observability\MetricsService;
use App\Payments\Infrastructure\Observability\NotificationService;
use App\Payments\Infrastructure\Observability\PaymentLogger;
use App\Payments\Infrastructure\Persistence\EloquentPaymentRepository;
use App\Payments\Infrastructure\Providers\AlfaBankProvider;
use App\Payments\Infrastructure\Providers\CloudPaymentsProvider;
use App\Payments\Infrastructure\Providers\RobokassaProvider;
use App\Payments\Infrastructure\Providers\SbpProvider;
use App\Payments\Infrastructure\Providers\YooKassaProvider;
use Illuminate\Support\ServiceProvider;

class PaymentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PaymentRepositoryInterface::class, EloquentPaymentRepository::class);
        $this->app->singleton(DisputeRepositoryInterface::class, EloquentDisputeRepository::class);
        $this->app->singleton(PaymentLogger::class);
        $this->app->singleton(MetricsService::class);
        $this->app->singleton(NotificationService::class);

        // ─── Individual provider singletons ───────────────────────────────────

        $this->app->singleton(YooKassaProvider::class, function () {
            return new YooKassaProvider(
                shopId:    config('payments.yookassa.shop_id'),
                secretKey: config('payments.yookassa.secret_key'),
                logger:    $this->app->make(PaymentLogger::class),
            );
        });

        $this->app->singleton(RobokassaProvider::class, function () {
            return new RobokassaProvider(
                login:     config('payments.robokassa.login'),
                password1: config('payments.robokassa.password1'),
                password2: config('payments.robokassa.password2'),
                isTest:    (bool) config('payments.robokassa.is_test', true),
                logger:    $this->app->make(PaymentLogger::class),
            );
        });

        $this->app->singleton(SbpProvider::class, function () {
            return new SbpProvider(
                merchantId:    config('payments.sbp.merchant_id'),
                apiKey:        config('payments.sbp.api_key'),
                webhookSecret: config('payments.sbp.webhook_secret'),
                baseUrl:       config('payments.sbp.base_url'),
                logger:        $this->app->make(PaymentLogger::class),
            );
        });

        $this->app->singleton(AlfaBankProvider::class, function () {
            return new AlfaBankProvider(
                login:      config('payments.alfabank.login'),
                password:   config('payments.alfabank.password'),
                baseUrl:    config('payments.alfabank.base_url'),
                logger:     $this->app->make(PaymentLogger::class),
                webhookIps: config('payments.alfabank.webhook_ips', []),
            );
        });

        $this->app->singleton(CloudPaymentsProvider::class, function () {
            return new CloudPaymentsProvider(
                publicId:  config('payments.cloudpayments.public_id'),
                apiSecret: config('payments.cloudpayments.api_secret'),
                logger:    $this->app->make(PaymentLogger::class),
            );
        });

        // ─── Registry: all providers registered in one place ─────────────────

        $this->app->singleton(PaymentProviderRegistry::class, function () {
            $registry = new PaymentProviderRegistry();
            $registry->register($this->app->make(YooKassaProvider::class));
            $registry->register($this->app->make(RobokassaProvider::class));
            $registry->register($this->app->make(SbpProvider::class));
            $registry->register($this->app->make(AlfaBankProvider::class));
            $registry->register($this->app->make(CloudPaymentsProvider::class));

            return $registry;
        });
    }

    public function boot(): void
    {
        $this->app['router']->aliasMiddleware('correlation', CorrelationIdMiddleware::class);
    }
}
