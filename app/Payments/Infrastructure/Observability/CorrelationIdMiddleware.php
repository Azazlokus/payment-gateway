<?php

declare(strict_types=1);

namespace App\Payments\Infrastructure\Observability;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class CorrelationIdMiddleware
{
    public function handle(Request $request, Closure $next): mixed
    {
        $correlationId = $request->header('X-Correlation-Id') ?? (string) Str::uuid();

        // Прокидываем в ответ чтобы клиент мог трейсить
        $response = $next($request);
        $response->headers->set('X-Correlation-Id', $correlationId);

        return $response;
    }
}
