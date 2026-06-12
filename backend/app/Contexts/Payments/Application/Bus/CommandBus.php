<?php

declare(strict_types=1);

namespace App\Contexts\Payments\Application\Bus;

use App\Contexts\Payments\Application\Pipeline\EnforceIdempotency;
use App\Contexts\Payments\Application\Pipeline\LogCommand;
use App\Contexts\Payments\Application\Pipeline\ValidateCommand;
use Illuminate\Pipeline\Pipeline;

class CommandBus
{
    /** @var array<class-string> */
    private array $pipes = [
        ValidateCommand::class,
        LogCommand::class,
        EnforceIdempotency::class,
    ];

    public function __construct(private readonly Pipeline $pipeline) {}

    public function dispatch(object $command): mixed
    {
        $handlerClass = $this->resolveHandler($command);
        $handler = app($handlerClass);

        return $this->pipeline
            ->send($command)
            ->through($this->pipes)
            ->then(fn (object $cmd) => $handler->handle($cmd));
    }

    private function resolveHandler(object $command): string
    {
        $commandClass = get_class($command);

        // App\Contexts\Payments\Application\Commands\CreatePayment\CreatePaymentCommand
        // →  App\Contexts\Payments\Application\Commands\CreatePayment\CreatePaymentHandler
        $handlerClass = preg_replace('/Command$/', 'Handler', $commandClass);

        if (! class_exists($handlerClass)) {
            throw new \RuntimeException(
                "No handler found for command: {$commandClass}. Expected: {$handlerClass}"
            );
        }

        return $handlerClass;
    }
}
