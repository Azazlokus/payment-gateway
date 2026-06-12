<?php

declare(strict_types=1);

namespace App\Contexts\Payments\Infrastructure\CircuitBreaker;

use App\Contexts\Payments\Application\DTOs\CreatePaymentOptionsDTO;
use App\Contexts\Payments\Domain\Contracts\PaymentProviderInterface;
use App\Contexts\Payments\Domain\Contracts\ProviderResponse;
use App\Contexts\Payments\Domain\Contracts\SupportsSplitPayments;
use App\Contexts\Payments\Domain\Contracts\SupportsTokenization;
use App\Contexts\Payments\Domain\Contracts\SupportsTwoPhasePayments;
use App\Contexts\Payments\Domain\Contracts\TokenizationResult;
use App\Contexts\Payments\Domain\Exceptions\PaymentException;
use App\Contexts\Payments\Domain\ValueObjects\ExternalId;
use App\Contexts\Payments\Domain\ValueObjects\Money;
use App\Contexts\Payments\Domain\ValueObjects\SplitRule;
use App\Contexts\Payments\Infrastructure\Observability\MetricsService;
use App\Contexts\Payments\Infrastructure\Observability\PaymentLogger;

final readonly class CircuitBreakerProviderProxy implements PaymentProviderInterface, SupportsSplitPayments, SupportsTokenization, SupportsTwoPhasePayments
{
    public function __construct(
        private PaymentProviderInterface $inner,
        private CircuitBreakerInterface $circuitBreaker,
        private PaymentLogger $logger,
        private MetricsService $metrics,
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
            fn (): ProviderResponse => $this->inner->createPayment($paymentId, $amount, $description, $returnUrl, $idempotencyKey, $options),
        );
    }

    public function getPayment(ExternalId $externalId): ProviderResponse
    {
        return $this->withCircuitBreaker(
            fn (): ProviderResponse => $this->inner->getPayment($externalId),
        );
    }

    public function refundPayment(ExternalId $externalId, Money $amount): ProviderResponse
    {
        return $this->withCircuitBreaker(
            fn (): ProviderResponse => $this->inner->refundPayment($externalId, $amount),
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

    // ─── SupportsTokenization ──────────────────────────────────────────────

    public function tokenize(string $paymentId): TokenizationResult
    {
        $inner = $this->guardInterface(SupportsTokenization::class);

        return $this->withCircuitBreaker(
            fn () => $inner->tokenize($paymentId),
        );
    }

    public function chargeToken(
        string $token,
        Money $amount,
        string $description,
        string $idempotencyKey,
    ): ProviderResponse {
        $inner = $this->guardInterface(SupportsTokenization::class);

        return $this->withCircuitBreaker(
            fn () => $inner->chargeToken($token, $amount, $description, $idempotencyKey),
        );
    }

    public function deleteToken(string $token): void
    {
        $inner = $this->guardInterface(SupportsTokenization::class);

        $this->withCircuitBreaker(function () use ($inner, $token): null {
            $inner->deleteToken($token);

            return null;
        });
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
