<?php

declare(strict_types=1);

namespace App\CryptoPayments\Infrastructure\Jobs;

use App\CryptoPayments\Application\ACL\CryptoDepositToPaymentAdapter;
use App\CryptoPayments\Domain\Aggregates\CryptoDeposit;
use App\CryptoPayments\Domain\Contracts\CryptoDepositRepositoryInterface;
use App\CryptoPayments\Domain\Events\DepositExpired;
use App\CryptoPayments\Infrastructure\Observability\CryptoMetricsService;
use App\Payments\Infrastructure\Observability\PaymentLogger;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

final class ExpireCryptoDepositsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [5, 15, 30];

    public function handle(
        CryptoDepositRepositoryInterface $deposits,
        CryptoDepositToPaymentAdapter $adapter,
        CryptoMetricsService $metrics,
        PaymentLogger $logger,
    ): void {
        /** @var CryptoDeposit[] $expired */
        $expired = $deposits->findExpired();

        foreach ($expired as $deposit) {
            try {
                $deposit->expire();
                $deposits->save($deposit);

                foreach ($deposit->pullDomainEvents() as $event) {
                    if ($event instanceof DepositExpired) {
                        $adapter->onDepositExpired($event);
                        $metrics->depositExpired($deposit->asset()->value);
                    }
                }

                $logger->info('ExpireCryptoDepositsJob: deposit expired', [
                    'deposit_id' => $deposit->id()->toString(),
                    'payment_id' => $deposit->paymentId(),
                ]);
            } catch (Throwable $e) {
                $logger->warning('ExpireCryptoDepositsJob: could not expire deposit', [
                    'deposit_id' => $deposit->id()->toString(),
                    'error'      => $e->getMessage(),
                ]);
            }
        }
    }

    public function failed(Throwable $exception): void
    {
        logger()->critical('ExpireCryptoDepositsJob permanently failed', [
            'error' => $exception->getMessage(),
        ]);
    }
}
