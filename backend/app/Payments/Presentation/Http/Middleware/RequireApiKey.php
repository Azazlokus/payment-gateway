<?php

declare(strict_types=1);

namespace App\Payments\Presentation\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RequireApiKey
{
    public function handle(Request $request, Closure $next): mixed
    {
        $configKey = (string) config('api.key');

        if ($configKey === '') {
            return $next($request);
        }

        $header = (string) $request->header('X-Api-Key', '');

        if (! hash_equals($configKey, $header)) {
            return response()->json([
                'code' => 'unauthorized',
                'message' => 'Invalid or missing API key',
            ], Response::HTTP_UNAUTHORIZED);
        }

        return $next($request);
    }
}
