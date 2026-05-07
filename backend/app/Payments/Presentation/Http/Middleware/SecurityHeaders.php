<?php

declare(strict_types=1);

namespace App\Payments\Presentation\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Добавляет защитные HTTP-заголовки к каждому ответу.
 *
 * X-Frame-Options       — защита от clickjacking
 * X-Content-Type-Options — запрет MIME-sniffing
 * Content-Security-Policy — ограничение источников ресурсов (XSS)
 * Referrer-Policy        — не утекает URL в сторонние сервисы
 * Permissions-Policy     — отключает ненужные browser API
 */
final class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');
        $response->headers->set('X-XSS-Protection', '0'); // CSP заменяет устаревший X-XSS-Protection

        $response->headers->set('Content-Security-Policy', implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline'",  // unsafe-inline нужен для Swagger UI / Horizon
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data: blob:",
            "font-src 'self' data:",
            "connect-src 'self'",
            "frame-ancestors 'none'",              // дублирует X-Frame-Options для современных браузеров
            "base-uri 'self'",
            "form-action 'self'",
        ]));

        return $response;
    }
}
