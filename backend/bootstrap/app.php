<?php

declare(strict_types=1);

use App\Payments\Domain\Exceptions\IdempotencyViolationException;
use App\Payments\Domain\Exceptions\InvalidPaymentStateException;
use App\Payments\Domain\Exceptions\PaymentException;
use App\Payments\Domain\Exceptions\WebhookVerificationFailedException;
use App\Payments\Infrastructure\Antifraud\VelocityLimitExceededException;
use App\Payments\Infrastructure\Observability\CorrelationIdMiddleware;
use App\Payments\Infrastructure\Observability\MetricsService;
use App\Payments\Presentation\Http\Middleware\RequireApiKey;
use App\Payments\Presentation\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->append(SecurityHeaders::class);

        $middleware->alias([
            'correlation' => CorrelationIdMiddleware::class,
            'auth.api' => RequireApiKey::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // ─── Helpers ─────────────────────────────────────────────────────────

        $traceId = fn (Request $request): ?string => $request->header('X-Correlation-Id');

        $errorJson = function (string $code, string $message, int $status, ?string $traceId) {
            $body = ['code' => $code, 'message' => $message];

            if ($traceId !== null) {
                $body['trace_id'] = $traceId;
            }

            return response()->json($body, $status);
        };

        // ─── Domain exceptions ────────────────────────────────────────────────

        // InvalidPaymentStateException — 409 Conflict
        $exceptions->render(function (
            InvalidPaymentStateException $e,
            Request $request,
        ) use ($traceId, $errorJson) {
            return $errorJson('invalid_payment_state', $e->getMessage(), Response::HTTP_CONFLICT, $traceId($request));
        });

        // WebhookVerificationFailedException — 403 Forbidden
        $exceptions->render(function (
            WebhookVerificationFailedException $e,
            Request $request,
        ) use ($traceId, $errorJson) {
            return $errorJson('webhook_verification_failed', $e->getMessage(), Response::HTTP_FORBIDDEN, $traceId($request));
        });

        // VelocityLimitExceededException — 429 Too Many Requests
        $exceptions->render(function (
            VelocityLimitExceededException $e,
            Request $request,
        ) use ($traceId, $errorJson) {
            return $errorJson('velocity_limit_exceeded', $e->getMessage(), Response::HTTP_TOO_MANY_REQUESTS, $traceId($request));
        });

        // IdempotencyViolationException — 409 Conflict
        $exceptions->render(function (
            IdempotencyViolationException $e,
            Request $request,
        ) use ($traceId, $errorJson) {
            return $errorJson('idempotency_violation', $e->getMessage(), Response::HTTP_CONFLICT, $traceId($request));
        });

        // PaymentException (base) — используем код из исключения
        $exceptions->render(function (
            PaymentException $e,
            Request $request,
        ) use ($traceId, $errorJson) {
            $status = ($e->getCode() >= 400 && $e->getCode() < 600)
                ? $e->getCode()
                : Response::HTTP_UNPROCESSABLE_ENTITY;

            return $errorJson('payment_error', $e->getMessage(), $status, $traceId($request));
        });

        // ─── Infrastructure exceptions ────────────────────────────────────────

        // ThrottleRequestsException — счётчик метрик + стандартный ответ
        $exceptions->render(function (
            ThrottleRequestsException $e,
            Request $request,
        ) use ($traceId, $errorJson) {
            try {
                app(MetricsService::class)->throttleRejected(
                    $request->route()?->getName() ?? $request->path()
                );
            } catch (Throwable) {
                // Не ломаем ответ если Redis недоступен
            }

            return $errorJson('throttle_exceeded', 'Too Many Requests', Response::HTTP_TOO_MANY_REQUESTS, $traceId($request));
        });
    })
    ->create();
