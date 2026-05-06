<?php

declare(strict_types=1);

namespace App\CryptoPayments\Infrastructure\Blockchain;

use App\CryptoPayments\Domain\Contracts\BlockchainClientInterface;
use App\CryptoPayments\Domain\Enums\CryptoAsset;
use App\CryptoPayments\Domain\Enums\DepositMode;
use App\CryptoPayments\Domain\ValueObjects\CryptoAddress;
use App\CryptoPayments\Domain\ValueObjects\Memo;
use App\CryptoPayments\Domain\ValueObjects\NativeCryptoAmount;
use App\CryptoPayments\Domain\ValueObjects\TransactionResult;
use App\CryptoPayments\Domain\ValueObjects\TxHash;
use App\Payments\Infrastructure\Observability\PaymentLogger;
use DateTimeImmutable;
use Illuminate\Support\Facades\Http;

final class BitcoinBlockchainClient implements BlockchainClientInterface
{
    public function __construct(
        private readonly string $apiUrl,
        private readonly array $addressPool,
        private readonly PaymentLogger $logger,
    ) {}

    public function network(): string
    {
        return 'bitcoin';
    }

    /** @return CryptoAsset[] */
    public function supportedAssets(): array
    {
        return [CryptoAsset::BTC];
    }

    public function depositMode(): DepositMode
    {
        return DepositMode::UniqueAddress;
    }

    public function masterDepositAddress(): CryptoAddress
    {
        return CryptoAddress::fromString($this->addressPool[0] ?? '1A1zP1eP5QGefi2DMPTfTL5SLmv7Divf8');
    }

    /** @return string[] */
    public function depositAddressPool(): array
    {
        return $this->addressPool;
    }

    /**
     * @param  Memo[] $memos
     * @return array<string, TransactionResult>
     */
    public function findIncomingTransactionsBatch(array $memos, CryptoAsset $asset, DateTimeImmutable $since): array
    {
        return [];
    }

    public function findIncomingTransaction(
        Memo $memo,
        CryptoAsset $asset,
        NativeCryptoAmount $expectedAmount,
        DateTimeImmutable $since,
    ): ?TransactionResult {
        return null;
    }

    public function findIncomingTransactionByAddress(
        CryptoAddress $address,
        CryptoAsset $asset,
        DateTimeImmutable $since,
    ): ?TransactionResult {
        $response = Http::get("{$this->apiUrl}/address/{$address->toString()}/txs");

        if (! $response->successful()) {
            $this->logger->warning('mempool.space API error', [
                'status'  => $response->status(),
                'address' => $address->toString(),
            ]);

            return null;
        }

        /** @var array<int, array<string, mixed>> $txs */
        $txs = $response->json() ?? [];

        foreach ($txs as $tx) {
            /** @var array<string, mixed> $status */
            $status = $tx['status'] ?? [];

            if (($status['confirmed'] ?? false) !== true) {
                continue;
            }

            $blockTime = (int) ($status['block_time'] ?? 0);

            if ($blockTime < $since->getTimestamp()) {
                continue;
            }

            $txid = $tx['txid'] ?? null;

            if (! is_string($txid) || $txid === '') {
                continue;
            }

            /** @var array<int, array<string, mixed>> $vout */
            $vout          = $tx['vout'] ?? [];
            $totalReceived = 0;

            foreach ($vout as $output) {
                if (($output['scriptpubkey_address'] ?? '') === $address->toString()) {
                    $totalReceived += (int) ($output['value'] ?? 0);
                }
            }

            if ($totalReceived <= 0) {
                continue;
            }

            return new TransactionResult(
                hash: TxHash::fromString($txid),
                actualAmount: NativeCryptoAmount::ofSatoshis($totalReceived),
                confirmedAt: new DateTimeImmutable("@{$blockTime}"),
            );
        }

        return null;
    }

    // ─── Sending (not supported — UTXO management requires external service) ──

    public function canSend(): bool
    {
        return false;
    }

    public function sendTransfer(
        CryptoAddress $to,
        NativeCryptoAmount $amount,
        CryptoAsset $asset,
        string $comment,
    ): TxHash {
        throw new \RuntimeException('BTC sending not supported: use an external UTXO signing service');
    }
}
