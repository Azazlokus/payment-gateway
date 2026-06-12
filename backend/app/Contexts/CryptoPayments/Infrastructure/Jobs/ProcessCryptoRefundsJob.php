<?php

declare(strict_types=1);

namespace App\Contexts\CryptoPayments\Infrastructure\Jobs;

use App\Contexts\CryptoPayments\Domain\Contracts\CryptoRefundRepositoryInterface;
use App\Contexts\CryptoPayments\Infrastructure\Blockchain\BlockchainClientRegistry;
use App\Contexts\Payments\Infrastructure\Observability\PaymentLogger;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

final class ProcessCryptoRefundsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [30, 60, 120];

    public function __construct()
    {
        // Очередь задаём в рантайме через onQueue(): переопределять свойство $queue
        // из трейта Queueable нельзя — отличающийся дефолт несовместим и роняет PHP.
        $this->onQueue('crypto');
    }

    public function handle(
        CryptoRefundRepositoryInterface $refunds,
        BlockchainClientRegistry $blockchain,
        PaymentLogger $logger,
    ): void {
        $pending = $refunds->findPending();

        if (empty($pending)) {
            return;
        }

        $logger->info('Processing crypto refunds', ['count' => count($pending)]);

        foreach ($pending as $refund) {
            try {
                $client = $blockchain->getForAsset($refund->asset());

                if (! $client->canSend()) {
                    $refund->markAsFailed("Hot wallet not configured for network '{$client->network()}'");
                    $refunds->save($refund);
                    $logger->warning('Crypto refund skipped: hot wallet not configured', [
                        'refund_id' => $refund->id()->toString(),
                        'network' => $client->network(),
                    ]);

                    continue;
                }

                $refund->markAsBroadcasting();
                $refunds->save($refund);

                $txHash = $client->sendTransfer(
                    to: $refund->toAddress(),
                    amount: $refund->amount(),
                    asset: $refund->asset(),
                    comment: "refund:{$refund->depositId()}",
                );

                $refund->markAsCompleted($txHash);
                $refunds->save($refund);

                $logger->info('Crypto refund completed', [
                    'refund_id' => $refund->id()->toString(),
                    'tx_hash' => $txHash->toString(),
                ]);
            } catch (Throwable $e) {
                $refund->markAsFailed($e->getMessage());
                $refunds->save($refund);

                $logger->error('Crypto refund failed', [
                    'refund_id' => $refund->id()->toString(),
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
