<?php

declare(strict_types=1);

namespace App\Payments\Application\Pipeline;

use App\Payments\Application\Commands\CreatePayment\CreatePaymentCommand;
use App\Payments\Domain\Contracts\PaymentRepositoryInterface;
use App\Payments\Application\DTOs\PaymentResultDTO;
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

        if ($existing !== null) {
            // Тихо возвращаем уже созданный платёж — не создаём дубль
            return PaymentResultDTO::fromAggregate($existing);
        }

        return $next($command);
    }
}
