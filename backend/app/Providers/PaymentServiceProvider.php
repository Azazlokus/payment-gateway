<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contexts\Payments\Application\PaymentProviderRegistry;
use App\Contexts\Payments\Domain\Contracts\DisputeRepositoryInterface;
use App\Contexts\Payments\Domain\Contracts\PaymentMethodRepositoryInterface;
use App\Contexts\Payments\Domain\Contracts\PaymentProviderInterface;
use App\Contexts\Payments\Domain\Contracts\PaymentRepositoryInterface;
use App\Contexts\Payments\Infrastructure\Antifraud\VelocityChecker;
use App\Contexts\Payments\Infrastructure\Antifraud\VelocityRule;
use App\Contexts\Payments\Infrastructure\CircuitBreaker\CircuitBreaker;
use App\Contexts\Payments\Infrastructure\CircuitBreaker\CircuitBreakerProviderProxy;
use App\Contexts\Payments\Infrastructure\Observability\AuditLogger;
use App\Contexts\Payments\Infrastructure\Observability\CorrelationIdMiddleware;
use App\Contexts\Payments\Infrastructure\Observability\MetricsService;
use App\Contexts\Payments\Infrastructure\Observability\NotificationService;
use App\Contexts\Payments\Infrastructure\Observability\PaymentLogger;
use App\Contexts\Payments\Infrastructure\Persistence\EloquentDisputeRepository;
use App\Contexts\Payments\Infrastructure\Persistence\EloquentPaymentMethodRepository;
use App\Contexts\Payments\Infrastructure\Persistence\EloquentPaymentRepository;
use App\Contexts\Payments\Infrastructure\Providers\AlfaBankProvider;
use App\Contexts\Payments\Infrastructure\Providers\CloudPaymentsProvider;
use App\Contexts\Payments\Infrastructure\Providers\RobokassaProvider;
use App\Contexts\Payments\Infrastructure\Providers\SbpProvider;
use App\Contexts\Payments\Infrastructure\Providers\YooKassaProvider;
use App\Contexts\Payments\Infrastructure\Tenant\TenantContext;
use App\Contexts\Payments\Infrastructure\Webhook\ReplayProtector;
use App\Contexts\Payments\Presentation\Http\Middleware\ResolveTenant;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class PaymentServiceProvider extends ServiceProvider
{
    #[\Override]
    public function register(): void
    {
        $this->app->singleton(TenantContext::class);
        $this->app->singleton(PaymentRepositoryInterface::class, EloquentPaymentRepository::class);
        $this->app->singleton(DisputeRepositoryInterface::class, EloquentDisputeRepository::class);
        $this->app->singleton(PaymentMethodRepositoryInterface::class, EloquentPaymentMethodRepository::class);
        $this->app->singleton(PaymentLogger::class);
        $this->app->singleton(MetricsService::class);
        $this->app->singleton(NotificationService::class);
        $this->app->singleton(AuditLogger::class);
        $this->app->singleton(ReplayProtector::class, fn ($app) => new ReplayProtector($app->make(Repository::class)));

        $this->app->singleton(VelocityChecker::class, function (): VelocityChecker {
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

        $this->app->singleton(CircuitBreaker::class, function (): CircuitBreaker {
            $config = config('payments.circuit_breaker', []);

            return new CircuitBreaker(
                failureThreshold: (int) ($config['failure_threshold'] ?? 5),
                recoveryTimeoutSeconds: (int) ($config['recovery_timeout_seconds'] ?? 30),
            );
        });

        // ─── Individual provider singletons ───────────────────────────────────

        $this->app->singleton(YooKassaProvider::class, fn () => new YooKassaProvider(
            shopId: config('payments.yookassa.shop_id'),
            secretKey: config('payments.yookassa.secret_key'),
            logger: $this->app->make(PaymentLogger::class),
        ));

        $this->app->singleton(RobokassaProvider::class, fn () => new RobokassaProvider(
            login: config('payments.robokassa.login'),
            password1: config('payments.robokassa.password1'),
            password2: config('payments.robokassa.password2'),
            isTest: (bool) config('payments.robokassa.is_test', true),
            logger: $this->app->make(PaymentLogger::class),
        ));

        $this->app->singleton(SbpProvider::class, fn () => new SbpProvider(
            merchantId: config('payments.sbp.merchant_id'),
            apiKey: config('payments.sbp.api_key'),
            webhookSecret: config('payments.sbp.webhook_secret'),
            baseUrl: config('payments.sbp.base_url'),
            logger: $this->app->make(PaymentLogger::class),
        ));

        $this->app->singleton(AlfaBankProvider::class, fn () => new AlfaBankProvider(
            login: config('payments.alfabank.login'),
            password: config('payments.alfabank.password'),
            baseUrl: config('payments.alfabank.base_url'),
            logger: $this->app->make(PaymentLogger::class),
            webhookIps: config('payments.alfabank.webhook_ips', []),
        ));

        $this->app->singleton(CloudPaymentsProvider::class, fn () => new CloudPaymentsProvider(
            publicId: config('payments.cloudpayments.public_id'),
            apiSecret: config('payments.cloudpayments.api_secret'),
            logger: $this->app->make(PaymentLogger::class),
        ));

        // ─── Registry: all providers registered in one place ─────────────────

        $this->app->singleton(PaymentProviderRegistry::class, function (): PaymentProviderRegistry {
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
        $this->app->make('router')->aliasMiddleware('correlation', CorrelationIdMiddleware::class);
        $this->app->make('router')->aliasMiddleware('resolve.tenant', ResolveTenant::class);

        RateLimiter::for('webhook.yookassa', fn () => Limit::perMinute(300));
        RateLimiter::for('webhook.robokassa', fn () => Limit::perMinute(200));
        RateLimiter::for('webhook.cloudpayments', fn () => Limit::perMinute(300));
        RateLimiter::for('webhook.sbp', fn () => Limit::perMinute(300));
        RateLimiter::for('webhook.alfabank', fn () => Limit::perMinute(200));
    }
}
