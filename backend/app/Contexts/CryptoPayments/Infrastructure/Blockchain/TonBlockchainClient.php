<?php

declare(strict_types=1);

namespace App\Contexts\CryptoPayments\Infrastructure\Blockchain;

use App\Contexts\CryptoPayments\Domain\Contracts\BlockchainClientInterface;
use App\Contexts\CryptoPayments\Domain\Enums\CryptoAsset;
use App\Contexts\CryptoPayments\Domain\Enums\DepositMode;
use App\Contexts\CryptoPayments\Domain\ValueObjects\CryptoAddress;
use App\Contexts\CryptoPayments\Domain\ValueObjects\Memo;
use App\Contexts\CryptoPayments\Domain\ValueObjects\NativeCryptoAmount;
use App\Contexts\CryptoPayments\Domain\ValueObjects\TonAddress;
use App\Contexts\CryptoPayments\Domain\ValueObjects\TransactionResult;
use App\Contexts\CryptoPayments\Domain\ValueObjects\TxHash;
use App\Contexts\Payments\Infrastructure\Observability\PaymentLogger;
use DateTimeImmutable;
use Illuminate\Support\Facades\Http;

final readonly class TonBlockchainClient implements BlockchainClientInterface
{
    public function __construct(
        private string $masterAddress,
        private string $apiKey,
        private string $apiUrl,
        private string $apiV3Url,
        private string $usdtJettonMaster,
        private PaymentLogger $logger,
    ) {}

    public function network(): string
    {
        return 'ton';
    }

    /** @return CryptoAsset[] */
    public function supportedAssets(): array
    {
        return [CryptoAsset::TON, CryptoAsset::USDT_TON];
    }

    public function depositMode(): DepositMode
    {
        return DepositMode::Memo;
    }

    public function masterDepositAddress(): CryptoAddress
    {
        return CryptoAddress::fromString(TonAddress::fromString($this->masterAddress)->toString());
    }

    /** @return string[] */
    public function depositAddressPool(): array
    {
        return [];
    }

    public function findIncomingTransactionByAddress(
        CryptoAddress $address,
        CryptoAsset $asset,
        DateTimeImmutable $since,
    ): ?TransactionResult {
        return null; // Not applicable for Memo-based mode
    }

    public function findIncomingTransaction(
        Memo $memo,
        CryptoAsset $asset,
        NativeCryptoAmount $expectedAmount,
        DateTimeImmutable $since,
    ): ?TransactionResult {
        $results = $this->findIncomingTransactionsBatch([$memo], $asset, $since);

        return $results[$memo->toString()] ?? null;
    }

    /**
     * @param  Memo[]  $memos
     * @return array<string, TransactionResult> key = memo string
     */
    public function findIncomingTransactionsBatch(
        array $memos,
        CryptoAsset $asset,
        DateTimeImmutable $since,
    ): array {
        return match ($asset) {
            CryptoAsset::TON => $this->fetchTonTransactions($memos, $since),
            CryptoAsset::USDT_TON => $this->fetchUsdtJettonTransfers($memos, $since),
            default => [],
        };
    }

    // ─── TON (native) ────────────────────────────────────────────────────────

    /**
     * Uses TonCenter v2 /getTransactions to find incoming TON transfers.
     * Matches by comment (memo) in in_msg.message or in_msg.msg_data.text.
     *
     * @param  Memo[]  $memos
     * @return array<string, TransactionResult>
     */
    private function fetchTonTransactions(array $memos, DateTimeImmutable $since): array
    {
        $memoSet = array_map(fn (Memo $m): string => $m->toString(), $memos);
        $found = [];

        $response = Http::withHeaders($this->authHeaders())->get("{$this->apiUrl}/getTransactions", [
            'address' => $this->masterAddress,
            'limit' => 50,
            'to_lt' => 0,
            'archival' => false,
        ]);

        if (! $response->successful()) {
            $this->logger->warning('TonCenter v2 API error', ['status' => $response->status()]);

            return [];
        }

        /** @var array<int, array<string, mixed>> $transactions */
        $transactions = $response->json('result', []);

        foreach ($transactions as $tx) {
            $utime = (int) ($tx['utime'] ?? 0);

            if ($utime < $since->getTimestamp()) {
                continue;
            }

            /** @var array<string, mixed>|null $inMsg */
            $inMsg = $tx['in_msg'] ?? null;

            if (! is_array($inMsg)) {
                continue;
            }

            $comment = $this->extractComment($inMsg);

            if ($comment === null || ! in_array($comment, $memoSet, true)) {
                continue;
            }

            /** @var array<string, mixed> $txId */
            $txId = $tx['transaction_id'] ?? [];
            $txHash = $txId['hash'] ?? null;

            if (! is_string($txHash) || $txHash === '') {
                continue;
            }

            $value = (int) ($inMsg['value'] ?? 0);

            if ($value <= 0) {
                continue;
            }

            $found[$comment] = new TransactionResult(
                hash: TxHash::fromString($txHash),
                actualAmount: NativeCryptoAmount::ofNanotons($value),
                confirmedAt: new DateTimeImmutable("@{$utime}"),
            );
        }

        return $found;
    }

    // ─── USDT-TON (Jetton) ───────────────────────────────────────────────────

    /**
     * Uses TonCenter v3 /jetton/transfers to find incoming USDT-TON Jetton transfers.
     *
     * The v3 API returns parsed Jetton transfer data including the comment (memo),
     * amount as a decimal string, and transaction hash — no BOC decoding needed.
     *
     * API docs: https://toncenter.com/api/v3/openapi.json
     *
     * @param  Memo[]  $memos
     * @return array<string, TransactionResult>
     */
    private function fetchUsdtJettonTransfers(array $memos, DateTimeImmutable $since): array
    {
        $memoSet = array_map(fn (Memo $m): string => $m->toString(), $memos);
        $found = [];

        $response = Http::withHeaders($this->authHeaders())->get("{$this->apiV3Url}/jetton/transfers", [
            'address' => $this->masterAddress,
            'jetton_master' => $this->usdtJettonMaster,
            'direction' => 'in',
            'start_utime' => $since->getTimestamp(),
            'limit' => 100,
            'offset' => 0,
        ]);

        if (! $response->successful()) {
            $this->logger->warning('TonCenter v3 API error (jetton/transfers)', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return [];
        }

        /** @var array<int, array<string, mixed>> $transfers */
        $transfers = $response->json('jetton_transfers', []);

        foreach ($transfers as $transfer) {
            $utime = (int) ($transfer['utime'] ?? 0);

            if ($utime < $since->getTimestamp()) {
                continue;
            }

            $comment = isset($transfer['comment']) && is_string($transfer['comment'])
                ? trim($transfer['comment'])
                : null;

            if ($comment === null || $comment === '' || ! in_array($comment, $memoSet, true)) {
                continue;
            }

            $txHash = $transfer['transaction_hash'] ?? null;

            if (! is_string($txHash) || $txHash === '') {
                continue;
            }

            // amount is a decimal string, e.g. "1000000" = 1 USDT (6 decimals)
            $amountStr = $transfer['amount'] ?? null;

            if (! is_string($amountStr) && ! is_int($amountStr)) {
                continue;
            }

            $amount = (int) $amountStr;

            if ($amount <= 0) {
                continue;
            }

            $found[$comment] = new TransactionResult(
                hash: TxHash::fromString($txHash),
                actualAmount: NativeCryptoAmount::ofMicroUsdt($amount),
                confirmedAt: new DateTimeImmutable("@{$utime}"),
            );
        }

        return $found;
    }

    // ─── Sending (hot wallet) ────────────────────────────────────────────────

    public function canSend(): bool
    {
        return config('crypto.ton.hot_wallet_mnemonic', '') !== '';
    }

    public function sendTransfer(
        CryptoAddress $to,
        NativeCryptoAmount $amount,
        CryptoAsset $asset,
        string $comment,
    ): TxHash {
        if (! $this->canSend()) {
            throw new \RuntimeException('TON hot wallet not configured: set TON_HOT_WALLET_MNEMONIC in .env');
        }

        // Full on-chain signing requires the olifanton/ton PHP SDK.
        // Install via: composer require olifanton/ton
        // Then: create wallet from mnemonic, sign transfer message, broadcast via /sendBoc.
        throw new \RuntimeException(
            'TON on-chain sending requires olifanton/ton SDK: composer require olifanton/ton'
        );
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    /** @return array<string, string> */
    private function authHeaders(): array
    {
        return $this->apiKey !== '' ? ['X-API-Key' => $this->apiKey] : [];
    }

    /** @param array<string, mixed> $inMsg */
    private function extractComment(array $inMsg): ?string
    {
        if (isset($inMsg['message']) && is_string($inMsg['message']) && $inMsg['message'] !== '') {
            return trim($inMsg['message']);
        }

        /** @var array<string, mixed> $msgData */
        $msgData = $inMsg['msg_data'] ?? [];

        if (
            ($msgData['@type'] ?? '') === 'msg.dataText'
            && isset($msgData['text'])
            && is_string($msgData['text'])
            && $msgData['text'] !== ''
        ) {
            $decoded = base64_decode($msgData['text'], true);

            return $decoded !== false ? trim($decoded) : null;
        }

        return null;
    }
}
