<?php

declare(strict_types=1);

namespace App\CryptoPayments\Infrastructure\Blockchain;

use App\CryptoPayments\Domain\Contracts\BlockchainClientInterface;
use App\CryptoPayments\Domain\Enums\CryptoAsset;
use App\CryptoPayments\Domain\ValueObjects\Memo;
use App\CryptoPayments\Domain\ValueObjects\NativeCryptoAmount;
use App\CryptoPayments\Domain\ValueObjects\TonAddress;
use App\CryptoPayments\Domain\ValueObjects\TransactionResult;
use App\CryptoPayments\Domain\ValueObjects\TxHash;
use App\Payments\Infrastructure\Observability\PaymentLogger;
use DateTimeImmutable;
use Illuminate\Support\Facades\Http;

final class TonBlockchainClient implements BlockchainClientInterface
{
    public function __construct(
        private readonly string $masterAddress,
        private readonly string $apiKey,
        private readonly string $apiUrl,
        private readonly string $apiV3Url,
        private readonly string $usdtJettonMaster,
        private readonly PaymentLogger $logger,
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

    public function masterDepositAddress(): TonAddress
    {
        return TonAddress::fromString($this->masterAddress);
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
     * @param  Memo[] $memos
     * @return array<string, TransactionResult>  key = memo string
     */
    public function findIncomingTransactionsBatch(
        array $memos,
        CryptoAsset $asset,
        DateTimeImmutable $since,
    ): array {
        return match ($asset) {
            CryptoAsset::TON      => $this->fetchTonTransactions($memos, $since),
            CryptoAsset::USDT_TON => $this->fetchUsdtJettonTransfers($memos, $since),
        };
    }

    // ─── TON (native) ────────────────────────────────────────────────────────

    /**
     * Uses TonCenter v2 /getTransactions to find incoming TON transfers.
     * Matches by comment (memo) in in_msg.message or in_msg.msg_data.text.
     *
     * @param  Memo[] $memos
     * @return array<string, TransactionResult>
     */
    private function fetchTonTransactions(array $memos, DateTimeImmutable $since): array
    {
        $memoSet = array_map(fn (Memo $m) => $m->toString(), $memos);
        $found   = [];

        $response = Http::withHeaders($this->authHeaders())->get("{$this->apiUrl}/getTransactions", [
            'address'  => $this->masterAddress,
            'limit'    => 50,
            'to_lt'    => 0,
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
            $txId   = $tx['transaction_id'] ?? [];
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
     * @param  Memo[] $memos
     * @return array<string, TransactionResult>
     */
    private function fetchUsdtJettonTransfers(array $memos, DateTimeImmutable $since): array
    {
        $memoSet = array_map(fn (Memo $m) => $m->toString(), $memos);
        $found   = [];

        $response = Http::withHeaders($this->authHeaders())->get("{$this->apiV3Url}/jetton/transfers", [
            'address'       => $this->masterAddress,
            'jetton_master' => $this->usdtJettonMaster,
            'direction'     => 'in',
            'start_utime'   => $since->getTimestamp(),
            'limit'         => 100,
            'offset'        => 0,
        ]);

        if (! $response->successful()) {
            $this->logger->warning('TonCenter v3 API error (jetton/transfers)', [
                'status' => $response->status(),
                'body'   => $response->body(),
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
            is_array($msgData)
            && ($msgData['@type'] ?? '') === 'msg.dataText'
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
