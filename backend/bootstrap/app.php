<?php

declare(strict_types=1);

use App\Payments\Infrastructure\Observability\MetricsService;
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
        $middleware->alias([
            'correlation' => \App\Payments\Infrastructure\Observability\CorrelationIdMiddleware::class,
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
            \App\Payments\Domain\Exceptions\InvalidPaymentStateException $e,
            Request $request,
        ) use ($traceId, $errorJson) {
            return $errorJson('invalid_payment_state', $e->getMessage(), Response::HTTP_CONFLICT, $traceId($request));
        });

        // WebhookVerificationFailedException — 403 Forbidden
        $exceptions->render(function (
            \App\Payments\Domain\Exceptions\WebhookVerificationFailedException $e,
            Request $request,
        ) use ($traceId, $errorJson) {
            return $errorJson('webhook_verification_failed', $e->getMessage(), Response::HTTP_FORBIDDEN, $traceId($request));
        });

        // IdempotencyViolationException — 409 Conflict
        $exceptions->render(function (
            \App\Payments\Domain\Exceptions\IdempotencyViolationException $e,
            Request $request,
        ) use ($traceId, $errorJson) {
            return $errorJson('idempotency_violation', $e->getMessage(), Response::HTTP_CONFLICT, $traceId($request));
        });

        // PaymentException (base) — используем код из исключения
        $exceptions->render(function (
            \App\Payments\Domain\Exceptions\PaymentException $e,
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
            } catch (\Throwable) {
                // Не ломаем ответ если Redis недоступен
            }

            return $errorJson('throttle_exceeded', 'Too Many Requests', Response::HTTP_TOO_MANY_REQUESTS, $traceId($request));
        });
    })
    ->create();
