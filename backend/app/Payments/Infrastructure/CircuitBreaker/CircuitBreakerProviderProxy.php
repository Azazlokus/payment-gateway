<?php

declare(strict_types=1);

namespace App\Payments\Infrastructure\CircuitBreaker;

use App\Payments\Application\DTOs\CreatePaymentOptionsDTO;
use App\Payments\Domain\Contracts\PaymentProviderInterface;
use App\Payments\Domain\Contracts\ProviderResponse;
use App\Payments\Domain\Contracts\SupportsSplitPayments;
use App\Payments\Domain\Contracts\SupportsTwoPhasePayments;
use App\Payments\Domain\Exceptions\PaymentException;
use App\Payments\Domain\ValueObjects\ExternalId;
use App\Payments\Domain\ValueObjects\Money;
use App\Payments\Domain\ValueObjects\SplitRule;
use App\Payments\Infrastructure\Observability\MetricsService;
use App\Payments\Infrastructure\Observability\PaymentLogger;

final class CircuitBreakerProviderProxy implements PaymentProviderInterface, SupportsSplitPayments, SupportsTwoPhasePayments
{
    public function __construct(
        private readonly PaymentProviderInterface $inner,
        private readonly CircuitBreakerInterface $circuitBreaker,
        private readonly PaymentLogger $logger,
        private readonly MetricsService $metrics,
    ) {}

    public function name(): string
    {
        return $this->inner->name();
    }

    public function createPayment(
        string $paymentId,
        Money $amount,
        string $description,
        string $returnUrl,
        string $idempotencyKey,
        CreatePaymentOptionsDTO $options = new CreatePaymentOptionsDTO,
    ): ProviderResponse {
        return $this->withCircuitBreaker(
            fn () => $this->inner->createPayment($paymentId, $amount, $description, $returnUrl, $idempotencyKey, $options),
        );
    }

    public function getPayment(ExternalId $externalId): ProviderResponse
    {
        return $this->withCircuitBreaker(
            fn () => $this->inner->getPayment($externalId),
        );
    }

    public function refundPayment(ExternalId $externalId, Money $amount): ProviderResponse
    {
        return $this->withCircuitBreaker(
            fn () => $this->inner->refundPayment($externalId, $amount),
        );
    }

    /** @param array<string, mixed> $payload
     *  @param array<string, list<string|null>> $headers */
    public function verifyWebhook(array $payload, array $headers): bool
    {
        // Webhook verification is local — no circuit breaker needed
        return $this->inner->verifyWebhook($payload, $headers);
    }

    /** @param array<string, mixed> $payload */
    public function parseWebhook(array $payload): ProviderResponse
    {
        // Webhook parsing is local — no circuit breaker needed
        return $this->inner->parseWebhook($payload);
    }

    // ─── SupportsTwoPhasePayments ───────────────────────────────────────────

    public function authorizePayment(
        string $paymentId,
        Money $amount,
        string $description,
        string $returnUrl,
        string $idempotencyKey,
        CreatePaymentOptionsDTO $options = new CreatePaymentOptionsDTO,
    ): ProviderResponse {
        $inner = $this->guardInterface(SupportsTwoPhasePayments::class);

        return $this->withCircuitBreaker(
            fn () => $inner->authorizePayment($paymentId, $amount, $description, $returnUrl, $idempotencyKey, $options),
        );
    }

    public function capturePayment(ExternalId $externalId, Money $amount): ProviderResponse
    {
        $inner = $this->guardInterface(SupportsTwoPhasePayments::class);

        return $this->withCircuitBreaker(
            fn () => $inner->capturePayment($externalId, $amount),
        );
    }

    public function voidPayment(ExternalId $externalId): ProviderResponse
    {
        $inner = $this->guardInterface(SupportsTwoPhasePayments::class);

        return $this->withCircuitBreaker(
            fn () => $inner->voidPayment($externalId),
        );
    }

    // ─── SupportsSplitPayments ──────────────────────────────────────────────

    /** @param SplitRule[] $splits */
    public function createSplitPayment(
        string $paymentId,
        Money $amount,
        string $description,
        string $returnUrl,
        string $idempotencyKey,
        array $splits,
        CreatePaymentOptionsDTO $options = new CreatePaymentOptionsDTO,
    ): ProviderResponse {
        $inner = $this->guardInterface(SupportsSplitPayments::class);

        return $this->withCircuitBreaker(
            fn () => $inner->createSplitPayment($paymentId, $amount, $description, $returnUrl, $idempotencyKey, $splits, $options),
        );
    }

    // ─── Core ───────────────────────────────────────────────────────────────

    /**
     * @template T
     *
     * @param  \Closure(): T  $action
     * @return T
     */
    private function withCircuitBreaker(\Closure $action): mixed
    {
        $service = $this->inner->name();

        if (! $this->circuitBreaker->isAvailable($service)) {
            $retryAfter = $this->circuitBreaker->retryAfterSeconds($service);

            $this->logger->warning('Circuit breaker open, rejecting request', [
                'provider' => $service,
                'retry_after_seconds' => $retryAfter,
            ]);

            $this->metrics->increment('circuit_breaker_rejected_total', ['provider' => $service]);

            throw new CircuitOpenException($service, $retryAfter);
        }

        try {
            $result = $action();
            $this->circuitBreaker->recordSuccess($service);

            return $result;
        } catch (CircuitOpenException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $this->circuitBreaker->recordFailure($service);

            $state = $this->circuitBreaker->getState($service);

            if ($state === CircuitState::Open) {
                $this->logger->error('Circuit breaker tripped', [
                    'provider' => $service,
                    'error' => $e->getMessage(),
                ]);

                $this->metrics->increment('circuit_breaker_tripped_total', ['provider' => $service]);
            }

            throw $e;
        }
    }

    /**
     * @template T of object
     *
     * @param  class-string<T>  $interface
     * @return T
     */
    private function guardInterface(string $interface): object
    {
        if (! $this->inner instanceof $interface) {
            throw new PaymentException(
                "Provider {$this->inner->name()} does not support ".class_basename($interface),
            );
        }

        return $this->inner;
    }
}
