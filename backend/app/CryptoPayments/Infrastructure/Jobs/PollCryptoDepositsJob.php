<?php

declare(strict_types=1);

namespace App\CryptoPayments\Infrastructure\Jobs;

use App\CryptoPayments\Application\ACL\CryptoDepositToPaymentAdapter;
use App\CryptoPayments\Domain\Aggregates\CryptoDeposit;
use App\CryptoPayments\Domain\Contracts\BlockchainClientInterface;
use App\CryptoPayments\Domain\Contracts\CryptoDepositRepositoryInterface;
use App\CryptoPayments\Domain\Enums\CryptoAsset;
use App\CryptoPayments\Domain\Enums\DepositMode;
use App\CryptoPayments\Domain\Events\DepositConfirmed;
use App\CryptoPayments\Domain\Events\DepositOverpaid;
use App\CryptoPayments\Domain\ValueObjects\CryptoAddress;
use App\CryptoPayments\Domain\ValueObjects\TransactionResult;
use App\CryptoPayments\Infrastructure\Blockchain\BlockchainClientRegistry;
use App\CryptoPayments\Infrastructure\Observability\CryptoMetricsService;
use App\Payments\Infrastructure\Observability\PaymentLogger;
use DateTimeImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

final class PollCryptoDepositsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [5, 15, 30];

    public function handle(
        CryptoDepositRepositoryInterface $deposits,
        BlockchainClientRegistry $blockchain,
        CryptoDepositToPaymentAdapter $adapter,
        CryptoMetricsService $metrics,
        PaymentLogger $logger,
    ): void {
        foreach (CryptoAsset::cases() as $asset) {
            $this->pollAsset($asset, $deposits, $blockchain, $adapter, $metrics, $logger);
        }
    }

    private function pollAsset(
        CryptoAsset $asset,
        CryptoDepositRepositoryInterface $deposits,
        BlockchainClientRegistry $blockchain,
        CryptoDepositToPaymentAdapter $adapter,
        CryptoMetricsService $metrics,
        PaymentLogger $logger,
    ): void {
        /** @var CryptoDeposit[] $pending */
        $pending = $deposits->findAwaitingByAsset($asset);

        if (empty($pending)) {
            return;
        }

        $client = $blockchain->getForAsset($asset);

        if ($client->depositMode() === DepositMode::UniqueAddress) {
            $this->pollUniqueAddressDeposits($pending, $asset, $client, $deposits, $adapter, $metrics, $logger);
        } else {
            $this->pollMemoDeposits($pending, $asset, $client, $deposits, $adapter, $metrics, $logger);
        }
    }

    /**
     * @param CryptoDeposit[] $pending
     */
    private function pollMemoDeposits(
        array $pending,
        CryptoAsset $asset,
        BlockchainClientInterface $client,
        CryptoDepositRepositoryInterface $deposits,
        CryptoDepositToPaymentAdapter $adapter,
        CryptoMetricsService $metrics,
        PaymentLogger $logger,
    ): void {
        // Build memo list and find earliest createdAt for the API query window
        $memos = [];
        $since = new DateTimeImmutable('now');

        foreach ($pending as $deposit) {
            $memo = $deposit->memo();
            if ($memo !== null) {
                $memos[] = $memo;
            }
            if ($deposit->createdAt() < $since) {
                $since = $deposit->createdAt();
            }
        }

        if (empty($memos)) {
            return;
        }

        try {
            $results = $client->findIncomingTransactionsBatch($memos, $asset, $since);
        } catch (Throwable $e) {
            $logger->warning('PollCryptoDepositsJob: blockchain query failed', [
                'asset' => $asset->value,
                'error' => $e->getMessage(),
            ]);

            return;
        }

        if (empty($results)) {
            return;
        }

        foreach ($pending as $deposit) {
            $memo = $deposit->memo();
            if ($memo === null) {
                continue;
            }

            $memoStr = $memo->toString();
            if (! isset($results[$memoStr])) {
                continue;
            }

            $tx = $results[$memoStr];
            $this->confirmDeposit($deposit, $tx, $asset, $deposits, $adapter, $metrics, $logger);
        }
    }

    /**
     * @param CryptoDeposit[] $pending
     */
    private function pollUniqueAddressDeposits(
        array $pending,
        CryptoAsset $asset,
        BlockchainClientInterface $client,
        CryptoDepositRepositoryInterface $deposits,
        CryptoDepositToPaymentAdapter $adapter,
        CryptoMetricsService $metrics,
        PaymentLogger $logger,
    ): void {
        foreach ($pending as $deposit) {
            $address = CryptoAddress::fromString($deposit->depositAddress()->toString());

            try {
                $tx = $client->findIncomingTransactionByAddress($address, $asset, $deposit->createdAt());
            } catch (Throwable $e) {
                $logger->warning('PollCryptoDepositsJob: blockchain query failed for address', [
                    'asset'   => $asset->value,
                    'address' => $address->toString(),
                    'error'   => $e->getMessage(),
                ]);
                continue;
            }

            if ($tx === null) {
                continue;
            }

            $this->confirmDeposit($deposit, $tx, $asset, $deposits, $adapter, $metrics, $logger);
        }
    }

    private function confirmDeposit(
        CryptoDeposit $deposit,
        TransactionResult $tx,
        CryptoAsset $asset,
        CryptoDepositRepositoryInterface $deposits,
        CryptoDepositToPaymentAdapter $adapter,
        CryptoMetricsService $metrics,
        PaymentLogger $logger,
    ): void {
        try {
            $deposit->confirm($tx->hash, $tx->actualAmount);
            $deposits->save($deposit);

            foreach ($deposit->pullDomainEvents() as $event) {
                if ($event instanceof DepositConfirmed) {
                    $adapter->onDepositConfirmed($event);
                    $metrics->depositConfirmed($asset->value);
                } elseif ($event instanceof DepositOverpaid) {
                    $adapter->onDepositOverpaid($event);
                    $metrics->depositOverpaid($asset->value);
                }
            }

            $logger->info('PollCryptoDepositsJob: deposit confirmed', [
                'deposit_id' => $deposit->id()->toString(),
                'tx_hash'    => $tx->hash->toString(),
                'asset'      => $asset->value,
            ]);
        } catch (Throwable $e) {
            $logger->warning('PollCryptoDepositsJob: could not confirm deposit', [
                'deposit_id' => $deposit->id()->toString(),
                'error'      => $e->getMessage(),
            ]);
        }
    }

    public function failed(Throwable $exception): void
    {
        logger()->critical('PollCryptoDepositsJob permanently failed', [
            'error' => $exception->getMessage(),
        ]);
    }
}
