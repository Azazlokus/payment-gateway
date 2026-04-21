<?php

declare(strict_types=1);

namespace Tests\Unit\CryptoPayments;

use App\CryptoPayments\Domain\Enums\CryptoAsset;
use App\CryptoPayments\Domain\ValueObjects\Memo;
use App\CryptoPayments\Domain\ValueObjects\NativeCryptoAmount;
use App\CryptoPayments\Infrastructure\Blockchain\TonBlockchainClient;
use App\Payments\Infrastructure\Observability\PaymentLogger;
use DateTimeImmutable;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

/**
 * Unit tests for TonBlockchainClient.
 *
 * All HTTP calls are intercepted via Http::fake() — no real network requests.
 */
class TonBlockchainClientTest extends TestCase
{
    private const MASTER   = 'UQAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAFy';
    private const USDT_CTR = 'EQCxE6mUtQJKFnGfaROTKOt1lZbDiiX1kCixRv7Nw2Id_sDs';
    private const TX_HASH  = 'abc123def456abc123def456abc123def456abc123def456abc123def456abc1';

    private TonBlockchainClient $client;

    protected function setUp(): void
    {
        parent::setUp();

        $logger = Mockery::mock(PaymentLogger::class);
        $logger->shouldReceive('warning')->byDefault();

        $this->client = new TonBlockchainClient(
            masterAddress: self::MASTER,
            apiKey: 'test-key',
            apiUrl: 'https://toncenter.test/api/v2',
            apiV3Url: 'https://toncenter.test/api/v3',
            usdtJettonMaster: self::USDT_CTR,
            logger: $logger,
        );
    }

    // ─── TON (v2) ────────────────────────────────────────────────────────────

    public function test_find_ton_transaction_by_memo(): void
    {
        $memo  = Memo::fromString('123456789');
        $since = new DateTimeImmutable('@0');

        Http::fake([
            '*/getTransactions*' => Http::response([
                'result' => [
                    $this->makeTonTx(memo: '123456789', value: 125_000_000, hash: self::TX_HASH, utime: 1_000),
                ],
            ]),
        ]);

        $results = $this->client->findIncomingTransactionsBatch([$memo], CryptoAsset::TON, $since);

        $this->assertArrayHasKey('123456789', $results);
        $this->assertSame(self::TX_HASH, $results['123456789']->hash->toString());
        $this->assertSame(125_000_000, $results['123456789']->actualAmount->units());
    }

    public function test_ton_ignores_transaction_outside_time_window(): void
    {
        $memo  = Memo::fromString('123456789');
        $since = new DateTimeImmutable('@9999999999');

        Http::fake([
            '*/getTransactions*' => Http::response([
                'result' => [
                    $this->makeTonTx(memo: '123456789', value: 125_000_000, hash: self::TX_HASH, utime: 1_000),
                ],
            ]),
        ]);

        $results = $this->client->findIncomingTransactionsBatch([$memo], CryptoAsset::TON, $since);

        $this->assertEmpty($results);
    }

    public function test_ton_ignores_transaction_with_wrong_memo(): void
    {
        $memo  = Memo::fromString('111111111');
        $since = new DateTimeImmutable('@0');

        Http::fake([
            '*/getTransactions*' => Http::response([
                'result' => [
                    $this->makeTonTx(memo: '999999999', value: 125_000_000, hash: self::TX_HASH, utime: 1_000),
                ],
            ]),
        ]);

        $results = $this->client->findIncomingTransactionsBatch([$memo], CryptoAsset::TON, $since);

        $this->assertEmpty($results);
    }

    public function test_ton_returns_empty_on_api_error(): void
    {
        $memo  = Memo::fromString('123456789');
        $since = new DateTimeImmutable('@0');

        Http::fake([
            '*/getTransactions*' => Http::response([], 500),
        ]);

        $results = $this->client->findIncomingTransactionsBatch([$memo], CryptoAsset::TON, $since);

        $this->assertEmpty($results);
    }

    public function test_ton_batch_matches_multiple_memos(): void
    {
        $memo1 = Memo::fromString('111111111');
        $memo2 = Memo::fromString('222222222');
        $since = new DateTimeImmutable('@0');

        Http::fake([
            '*/getTransactions*' => Http::response([
                'result' => [
                    $this->makeTonTx(memo: '111111111', value: 100_000_000, hash: 'aaaa' . str_repeat('0', 60), utime: 1_000),
                    $this->makeTonTx(memo: '222222222', value: 200_000_000, hash: 'bbbb' . str_repeat('0', 60), utime: 2_000),
                ],
            ]),
        ]);

        $results = $this->client->findIncomingTransactionsBatch([$memo1, $memo2], CryptoAsset::TON, $since);

        $this->assertArrayHasKey('111111111', $results);
        $this->assertArrayHasKey('222222222', $results);
        $this->assertSame(100_000_000, $results['111111111']->actualAmount->units());
        $this->assertSame(200_000_000, $results['222222222']->actualAmount->units());
    }

    public function test_ton_reads_memo_from_base64_msg_data(): void
    {
        $memo  = Memo::fromString('123456789');
        $since = new DateTimeImmutable('@0');

        $tx = $this->makeTonTx(memo: null, value: 125_000_000, hash: self::TX_HASH, utime: 1_000);
        // Override in_msg to use msg_data.text (base64)
        $tx['in_msg']['message'] = null;
        $tx['in_msg']['msg_data'] = [
            '@type' => 'msg.dataText',
            'text'  => base64_encode('123456789'),
        ];

        Http::fake([
            '*/getTransactions*' => Http::response(['result' => [$tx]]),
        ]);

        $results = $this->client->findIncomingTransactionsBatch([$memo], CryptoAsset::TON, $since);

        $this->assertArrayHasKey('123456789', $results);
    }

    // ─── USDT-TON (v3 Jetton) ────────────────────────────────────────────────

    public function test_find_usdt_transfer_by_memo(): void
    {
        $memo  = Memo::fromString('123456789');
        $since = new DateTimeImmutable('@0');

        Http::fake([
            '*/jetton/transfers*' => Http::response([
                'jetton_transfers' => [
                    $this->makeUsdtTransfer(memo: '123456789', amount: '1000000', hash: self::TX_HASH, utime: 1_000),
                ],
            ]),
        ]);

        $results = $this->client->findIncomingTransactionsBatch([$memo], CryptoAsset::USDT_TON, $since);

        $this->assertArrayHasKey('123456789', $results);
        $this->assertSame(self::TX_HASH, $results['123456789']->hash->toString());
        $this->assertSame(1_000_000, $results['123456789']->actualAmount->units());
        $this->assertSame(CryptoAsset::USDT_TON, $results['123456789']->actualAmount->asset());
    }

    public function test_usdt_ignores_transfer_outside_time_window(): void
    {
        $memo  = Memo::fromString('123456789');
        $since = new DateTimeImmutable('@9999999999');

        Http::fake([
            '*/jetton/transfers*' => Http::response([
                'jetton_transfers' => [
                    $this->makeUsdtTransfer(memo: '123456789', amount: '1000000', hash: self::TX_HASH, utime: 1_000),
                ],
            ]),
        ]);

        $results = $this->client->findIncomingTransactionsBatch([$memo], CryptoAsset::USDT_TON, $since);

        $this->assertEmpty($results);
    }

    public function test_usdt_ignores_transfer_with_wrong_memo(): void
    {
        $memo  = Memo::fromString('111111111');
        $since = new DateTimeImmutable('@0');

        Http::fake([
            '*/jetton/transfers*' => Http::response([
                'jetton_transfers' => [
                    $this->makeUsdtTransfer(memo: '999999999', amount: '1000000', hash: self::TX_HASH, utime: 1_000),
                ],
            ]),
        ]);

        $results = $this->client->findIncomingTransactionsBatch([$memo], CryptoAsset::USDT_TON, $since);

        $this->assertEmpty($results);
    }

    public function test_usdt_returns_empty_on_api_error(): void
    {
        $memo  = Memo::fromString('123456789');
        $since = new DateTimeImmutable('@0');

        Http::fake([
            '*/jetton/transfers*' => Http::response([], 503),
        ]);

        $results = $this->client->findIncomingTransactionsBatch([$memo], CryptoAsset::USDT_TON, $since);

        $this->assertEmpty($results);
    }

    public function test_usdt_ignores_transfer_with_zero_amount(): void
    {
        $memo  = Memo::fromString('123456789');
        $since = new DateTimeImmutable('@0');

        Http::fake([
            '*/jetton/transfers*' => Http::response([
                'jetton_transfers' => [
                    $this->makeUsdtTransfer(memo: '123456789', amount: '0', hash: self::TX_HASH, utime: 1_000),
                ],
            ]),
        ]);

        $results = $this->client->findIncomingTransactionsBatch([$memo], CryptoAsset::USDT_TON, $since);

        $this->assertEmpty($results);
    }

    public function test_usdt_batch_matches_multiple_memos(): void
    {
        $memo1 = Memo::fromString('111111111');
        $memo2 = Memo::fromString('222222222');
        $since = new DateTimeImmutable('@0');

        Http::fake([
            '*/jetton/transfers*' => Http::response([
                'jetton_transfers' => [
                    $this->makeUsdtTransfer(memo: '111111111', amount: '500000', hash: 'aaaa' . str_repeat('0', 60), utime: 1_000),
                    $this->makeUsdtTransfer(memo: '222222222', amount: '750000', hash: 'bbbb' . str_repeat('0', 60), utime: 2_000),
                ],
            ]),
        ]);

        $results = $this->client->findIncomingTransactionsBatch([$memo1, $memo2], CryptoAsset::USDT_TON, $since);

        $this->assertArrayHasKey('111111111', $results);
        $this->assertArrayHasKey('222222222', $results);
        $this->assertSame(500_000, $results['111111111']->actualAmount->units());
        $this->assertSame(750_000, $results['222222222']->actualAmount->units());
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    /**
     * @return array<string, mixed>
     */
    private function makeTonTx(?string $memo, int $value, string $hash, int $utime): array
    {
        return [
            'utime'          => $utime,
            'transaction_id' => ['hash' => $hash],
            'in_msg'         => [
                'value'   => $value,
                'message' => $memo,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function makeUsdtTransfer(string $memo, string $amount, string $hash, int $utime): array
    {
        return [
            'utime'            => $utime,
            'transaction_hash' => $hash,
            'amount'           => $amount,
            'comment'          => $memo,
        ];
    }
}
