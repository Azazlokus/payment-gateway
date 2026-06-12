<?php

declare(strict_types=1);

namespace App\Contexts\Payments\Application\Pipeline;

use Closure;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class ValidateCommand
{
    public function handle(object $command, Closure $next): mixed
    {
        if (! method_exists($command, 'rules')) {
            return $next($command);
        }

        $validator = Validator::make(
            (array) $command,
            $command->rules(),
        );

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $next($command);
    }
}
