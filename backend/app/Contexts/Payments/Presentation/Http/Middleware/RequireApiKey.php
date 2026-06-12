<?php

declare(strict_types=1);

namespace App\Contexts\Payments\Presentation\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RequireApiKey
{
    public function handle(Request $request, Closure $next): mixed
    {
        // Поддержка ротации ключей: API_KEY=old-key,new-key
        // Оба ключа принимаются одновременно — позволяет плавно ротировать
        // без даунтайма. После обновления клиентов старый ключ удаляется.
        $configKeys = array_values(array_filter(
            array_map(trim(...), explode(',', (string) config('api.key')))
        ));

        if ($configKeys === []) {
            return $next($request);
        }

        $header = (string) $request->header('X-Api-Key', '');

        foreach ($configKeys as $key) {
            if (hash_equals($key, $header)) {
                return $next($request);
            }
        }

        return response()->json([
            'code' => 'unauthorized',
            'message' => 'Invalid or missing API key',
        ], Response::HTTP_UNAUTHORIZED);
    }
}
