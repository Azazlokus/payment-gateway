<?php

declare(strict_types=1);

namespace App\Contexts\Payments\Application\Pipeline;

use App\Contexts\Payments\Application\Commands\CreatePayment\CreatePaymentCommand;
use App\Contexts\Payments\Application\DTOs\PaymentResultDTO;
use App\Contexts\Payments\Domain\Aggregates\Payment;
use App\Contexts\Payments\Domain\Contracts\PaymentRepositoryInterface;
use Closure;

final readonly class EnforceIdempotency
{
    public function __construct(
        private PaymentRepositoryInterface $repository,
    ) {}

    public function handle(object $command, Closure $next): mixed
    {
        if (! $command instanceof CreatePaymentCommand) {
            return $next($command);
        }

        $existing = $this->repository->findByIdempotencyKey($command->idempotencyKey);

        if ($existing instanceof Payment) {
            // Тихо возвращаем уже созданный платёж — не создаём дубль
            return PaymentResultDTO::fromAggregate($existing);
        }

        return $next($command);
    }
}
