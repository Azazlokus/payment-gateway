<?php

declare(strict_types=1);

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
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
        // InvalidPaymentStateException всегда 409 Conflict
        $exceptions->render(function (
            \App\Payments\Domain\Exceptions\InvalidPaymentStateException $e
        ) {
            return response()->json([
                'error' => 'invalid_payment_state',
                'message' => $e->getMessage(),
            ], Response::HTTP_CONFLICT);
        });

        // PaymentException — используем код из исключения (404, 422 и т.д.)
        $exceptions->render(function (
            \App\Payments\Domain\Exceptions\PaymentException $e
        ) {
            $status = ($e->getCode() >= 400 && $e->getCode() < 600)
                ? $e->getCode()
                : Response::HTTP_UNPROCESSABLE_ENTITY;

            return response()->json([
                'error' => 'payment_error',
                'message' => $e->getMessage(),
            ], $status);
        });
    })
    ->create();
