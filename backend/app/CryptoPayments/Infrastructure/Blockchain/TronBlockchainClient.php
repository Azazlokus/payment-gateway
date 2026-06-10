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

final class TronBlockchainClient implements BlockchainClientInterface
{
    /** @param array<string> $addressPool */
    public function __construct(
        private readonly string $apiUrl,
        private readonly string $apiKey,
        private readonly string $usdtContract,
        private readonly array $addressPool,
        private readonly PaymentLogger $logger,
    ) {}

    public function network(): string
    {
        return 'tron';
    }

    /** @return CryptoAsset[] */
    public function supportedAssets(): array
    {
        return [CryptoAsset::TRX, CryptoAsset::USDT_TRC20];
    }

    public function depositMode(): DepositMode
    {
        return DepositMode::UniqueAddress;
    }

    public function masterDepositAddress(): CryptoAddress
    {
        // Not used in UniqueAddress mode, return placeholder from pool
        return CryptoAddress::fromString($this->addressPool[0] ?? 'T000000000000000000000000000000000');
    }

    /** @return string[] */
    public function depositAddressPool(): array
    {
        return $this->addressPool;
    }

    /**
     * @param  Memo[]  $memos
     * @return array<string, TransactionResult>
     */
    public function findIncomingTransactionsBatch(array $memos, CryptoAsset $asset, DateTimeImmutable $since): array
    {
        return []; // UniqueAddress mode — not used
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
        return match ($asset) {
            CryptoAsset::TRX => $this->fetchTrxTransaction($address, $since),
            CryptoAsset::USDT_TRC20 => $this->fetchUsdtTrc20Transaction($address, $since),
            default => null,
        };
    }

    private function fetchTrxTransaction(CryptoAddress $address, DateTimeImmutable $since): ?TransactionResult
    {
        $minTimestampMs = $since->getTimestamp() * 1000;
        $response = Http::withHeaders($this->authHeaders())
            ->get("{$this->apiUrl}/v1/accounts/{$address->toString()}/transactions", [
                'only_to' => 'true',
                'limit' => 50,
                'min_timestamp' => $minTimestampMs,
            ]);

        if (! $response->successful()) {
            $this->logger->warning('TronGrid TRX API error', [
                'status' => $response->status(),
                'address' => $address->toString(),
            ]);

            return null;
        }

        /** @var array<int, array<string, mixed>> $txs */
        $txs = $response->json('data', []);

        foreach ($txs as $tx) {
            /** @var array<int, array<string, string>> $ret */
            $ret = $tx['ret'] ?? [];

            if (($ret[0]['contractRet'] ?? '') !== 'SUCCESS') {
                continue;
            }

            /** @var array<string, mixed> $rawData */
            $rawData = $tx['raw_data'] ?? [];

            /** @var array<int, array<string, mixed>> $contracts */
            $contracts = $rawData['contract'] ?? [];
            $contract = $contracts[0] ?? [];

            if (($contract['type'] ?? '') !== 'TransferContract') {
                continue;
            }

            /** @var array<string, mixed> $param */
            $param = $contract['parameter']['value'] ?? [];
            $amount = (int) ($param['amount'] ?? 0);

            if ($amount <= 0) {
                continue;
            }

            $txHash = $tx['txID'] ?? null;

            if (! is_string($txHash) || $txHash === '') {
                continue;
            }

            $blockTs = (int) ($rawData['timestamp'] ?? 0);
            $utime = (int) ($blockTs / 1000);

            return new TransactionResult(
                hash: TxHash::fromString($txHash),
                actualAmount: NativeCryptoAmount::ofSunUnits($amount),
                confirmedAt: new DateTimeImmutable("@{$utime}"),
            );
        }

        return null;
    }

    private function fetchUsdtTrc20Transaction(CryptoAddress $address, DateTimeImmutable $since): ?TransactionResult
    {
        $minTimestampMs = $since->getTimestamp() * 1000;
        $response = Http::withHeaders($this->authHeaders())
            ->get("{$this->apiUrl}/v1/accounts/{$address->toString()}/transactions/trc20", [
                'contract_address' => $this->usdtContract,
                'only_to' => 'true',
                'limit' => 50,
                'min_timestamp' => $minTimestampMs,
            ]);

        if (! $response->successful()) {
            $this->logger->warning('TronGrid USDT-TRC20 API error', [
                'status' => $response->status(),
                'address' => $address->toString(),
            ]);

            return null;
        }

        /** @var array<int, array<string, mixed>> $transfers */
        $transfers = $response->json('data', []);

        foreach ($transfers as $transfer) {
            $amountStr = $transfer['value'] ?? null;

            if (! is_string($amountStr) && ! is_int($amountStr)) {
                continue;
            }

            $amount = (int) $amountStr;

            if ($amount <= 0) {
                continue;
            }

            $txHash = $transfer['transaction_id'] ?? null;

            if (! is_string($txHash) || $txHash === '') {
                continue;
            }

            $blockTs = (int) ($transfer['block_timestamp'] ?? 0);
            $utime = (int) ($blockTs / 1000);

            if ($utime < $since->getTimestamp()) {
                continue;
            }

            return new TransactionResult(
                hash: TxHash::fromString($txHash),
                actualAmount: NativeCryptoAmount::ofMicroUsdtTrc20($amount),
                confirmedAt: new DateTimeImmutable("@{$utime}"),
            );
        }

        return null;
    }

    // ─── Sending (hot wallet) ────────────────────────────────────────────────

    public function canSend(): bool
    {
        return config('crypto.tron.hot_wallet_private_key', '') !== '';
    }

    public function sendTransfer(
        CryptoAddress $to,
        NativeCryptoAmount $amount,
        CryptoAsset $asset,
        string $comment,
    ): TxHash {
        if (! $this->canSend()) {
            throw new \RuntimeException('TRON hot wallet not configured: set TRON_HOT_WALLET_PRIVATE_KEY in .env');
        }

        // Full TRON sending: POST /wallet/createtransaction → sign with secp256k1 → POST /wallet/broadcasttransaction
        // Signing requires a secp256k1 library (e.g. composer require kornrunner/keccak + raw openssl).
        throw new \RuntimeException(
            'TRON on-chain sending requires a secp256k1 signing library'
        );
    }

    /** @return array<string, string> */
    private function authHeaders(): array
    {
        return $this->apiKey !== '' ? ['TRON-PRO-API-KEY' => $this->apiKey] : [];
    }
}
