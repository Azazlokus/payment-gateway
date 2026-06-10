<?php

declare(strict_types=1);

namespace App\Providers;

use App\Payments\Application\PaymentProviderRegistry;
use App\Payments\Infrastructure\Antifraud\VelocityChecker;
use App\Payments\Infrastructure\Antifraud\VelocityRule;
use App\Payments\Domain\Contracts\DisputeRepositoryInterface;
use App\Payments\Domain\Contracts\PaymentProviderInterface;
use App\Payments\Domain\Contracts\PaymentRepositoryInterface;
use App\Payments\Infrastructure\CircuitBreaker\CircuitBreaker;
use App\Payments\Infrastructure\CircuitBreaker\CircuitBreakerProviderProxy;
use App\Payments\Infrastructure\Observability\AuditLogger;
use App\Payments\Infrastructure\Observability\CorrelationIdMiddleware;
use App\Payments\Infrastructure\Observability\MetricsService;
use App\Payments\Infrastructure\Observability\NotificationService;
use App\Payments\Infrastructure\Observability\PaymentLogger;
use App\Payments\Infrastructure\Persistence\EloquentDisputeRepository;
use App\Payments\Infrastructure\Persistence\EloquentPaymentRepository;
use App\Payments\Infrastructure\Providers\AlfaBankProvider;
use App\Payments\Infrastructure\Providers\CloudPaymentsProvider;
use App\Payments\Infrastructure\Providers\RobokassaProvider;
use App\Payments\Infrastructure\Providers\SbpProvider;
use App\Payments\Infrastructure\Providers\YooKassaProvider;
use App\Payments\Infrastructure\Webhook\ReplayProtector;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\RateLimiter;
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
        $this->app->singleton(AuditLogger::class);
        $this->app->singleton(ReplayProtector::class, function ($app) {
            return new ReplayProtector($app->make(Repository::class));
        });

        $this->app->singleton(VelocityChecker::class, function () {
            $checker = new VelocityChecker(
                logger: $this->app->make(PaymentLogger::class),
                metrics: $this->app->make(MetricsService::class),
            );

            if (config('payments.antifraud.enabled', true)) {
                foreach (config('payments.antifraud.rules', []) as $rule) {
                    $checker->addRule(new VelocityRule(
                        dimension: $rule['dimension'],
                        maxCount: (int) $rule['max_count'],
                        windowSeconds: (int) $rule['window_seconds'],
                        maxAmountKopecks: isset($rule['max_amount_kopecks']) ? (int) $rule['max_amount_kopecks'] : null,
                    ));
                }
            }

            return $checker;
        });

        $this->app->singleton(CircuitBreaker::class, function () {
            $config = config('payments.circuit_breaker', []);

            return new CircuitBreaker(
                failureThreshold: (int) ($config['failure_threshold'] ?? 5),
                recoveryTimeoutSeconds: (int) ($config['recovery_timeout_seconds'] ?? 30),
            );
        });

        // ─── Individual provider singletons ───────────────────────────────────

        $this->app->singleton(YooKassaProvider::class, function () {
            return new YooKassaProvider(
                shopId: config('payments.yookassa.shop_id'),
                secretKey: config('payments.yookassa.secret_key'),
                logger: $this->app->make(PaymentLogger::class),
            );
        });

        $this->app->singleton(RobokassaProvider::class, function () {
            return new RobokassaProvider(
                login: config('payments.robokassa.login'),
                password1: config('payments.robokassa.password1'),
                password2: config('payments.robokassa.password2'),
                isTest: (bool) config('payments.robokassa.is_test', true),
                logger: $this->app->make(PaymentLogger::class),
            );
        });

        $this->app->singleton(SbpProvider::class, function () {
            return new SbpProvider(
                merchantId: config('payments.sbp.merchant_id'),
                apiKey: config('payments.sbp.api_key'),
                webhookSecret: config('payments.sbp.webhook_secret'),
                baseUrl: config('payments.sbp.base_url'),
                logger: $this->app->make(PaymentLogger::class),
            );
        });

        $this->app->singleton(AlfaBankProvider::class, function () {
            return new AlfaBankProvider(
                login: config('payments.alfabank.login'),
                password: config('payments.alfabank.password'),
                baseUrl: config('payments.alfabank.base_url'),
                logger: $this->app->make(PaymentLogger::class),
                webhookIps: config('payments.alfabank.webhook_ips', []),
            );
        });

        $this->app->singleton(CloudPaymentsProvider::class, function () {
            return new CloudPaymentsProvider(
                publicId: config('payments.cloudpayments.public_id'),
                apiSecret: config('payments.cloudpayments.api_secret'),
                logger: $this->app->make(PaymentLogger::class),
            );
        });

        // ─── Registry: all providers registered in one place ─────────────────

        $this->app->singleton(PaymentProviderRegistry::class, function () {
            $registry = new PaymentProviderRegistry;

            $providers = [
                $this->app->make(YooKassaProvider::class),
                $this->app->make(RobokassaProvider::class),
                $this->app->make(SbpProvider::class),
                $this->app->make(AlfaBankProvider::class),
                $this->app->make(CloudPaymentsProvider::class),
            ];

            foreach ($providers as $provider) {
                $registry->register($this->wrapWithCircuitBreaker($provider));
            }

            return $registry;
        });
    }

    private function wrapWithCircuitBreaker(PaymentProviderInterface $provider): PaymentProviderInterface
    {
        if (! config('payments.circuit_breaker.enabled', true)) {
            return $provider;
        }

        return new CircuitBreakerProviderProxy(
            inner: $provider,
            circuitBreaker: $this->app->make(CircuitBreaker::class),
            logger: $this->app->make(PaymentLogger::class),
            metrics: $this->app->make(MetricsService::class),
        );
    }

    public function boot(): void
    {
        $this->app['router']->aliasMiddleware('correlation', CorrelationIdMiddleware::class);

        RateLimiter::for('webhook.yookassa', fn () => Limit::perMinute(300));
        RateLimiter::for('webhook.robokassa', fn () => Limit::perMinute(200));
        RateLimiter::for('webhook.cloudpayments', fn () => Limit::perMinute(300));
        RateLimiter::for('webhook.sbp', fn () => Limit::perMinute(300));
        RateLimiter::for('webhook.alfabank', fn () => Limit::perMinute(200));
    }
}
