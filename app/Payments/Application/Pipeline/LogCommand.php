<?php

declare(strict_types=1);

namespace App\Payments\Application\Pipeline;

use Closure;
use Illuminate\Support\Facades\Log;

final class LogCommand
{
    public function handle(object $command, Closure $next): mixed
    {
        $start = microtime(true);

        Log::channel('payments')->info('Command dispatched', [
            'command'        => class_basename($command),
            'correlation_id' => request()->header('X-Correlation-Id'),
        ]);

        $result = $next($command);

        Log::channel('payments')->info('Command completed', [
            'command'        => class_basename($command),
            'duration_ms'    => round((microtime(true) - $start) * 1000, 2),
        ]);

        return $result;
    }
}
