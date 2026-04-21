<?php

declare(strict_types=1);

namespace Tests\Unit\CryptoPayments;

use App\CryptoPayments\Domain\Enums\CryptoAsset;
use App\CryptoPayments\Domain\ValueObjects\CryptoAddress;
use App\CryptoPayments\Infrastructure\Blockchain\TronBlockchainClient;
use App\Payments\Infrastructure\Observability\PaymentLogger;
use DateTimeImmutable;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

/**
 * Unit tests for TronBlockchainClient.
 *
 * All HTTP calls are intercepted via Http::fake() — no real network requests.
 */
class TronBlockchainClientTest extends TestCase
{
    private const TRON_ADDRESS = 'TN3W4H6rK2ce4vX9YnFQHwKENnHjoxb3m9';
    private const TX_HASH      = 'abc123def456abc123def456abc123def456abc123def456abc123def456abcd';
    private const USDT_CTR     = 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t';

    private TronBlockchainClient $client;

    protected function setUp(): void
    {
        parent::setUp();

        $logger = Mockery::mock(PaymentLogger::class);
        $logger->shouldReceive('warning')->byDefault();

        $this->client = new TronBlockchainClient(
            apiUrl: 'https://api.trongrid.test',
            apiKey: 'test-key',
            usdtContract: self::USDT_CTR,
            addressPool: [self::TRON_ADDRESS],
            logger: $logger,
        );
    }

    // ─── TRX ─────────────────────────────────────────────────────────────────

    public function test_find_trx_transaction_for_address(): void
    {
        $address = CryptoAddress::fromString(self::TRON_ADDRESS);
        $since   = new DateTimeImmutable('@0');

        Http::fake([
            '*/v1/accounts/*/transactions*' => Http::response([
                'data' => [
                    $this->makeTrxTx(
                        hash: self::TX_HASH,
                        amount: 5_000_000,
                        timestampMs: 1_000_000,
                    ),
                ],
            ]),
        ]);

        $result = $this->client->findIncomingTransactionByAddress($address, CryptoAsset::TRX, $since);

        $this->assertNotNull($result);
        $this->assertSame(self::TX_HASH, $result->hash->toString());
        $this->assertSame(5_000_000, $result->actualAmount->units());
        $this->assertSame(CryptoAsset::TRX, $result->actualAmount->asset());
    }

    public function test_trx_returns_null_when_no_transactions(): void
    {
        $address = CryptoAddress::fromString(self::TRON_ADDRESS);
        $since   = new DateTimeImmutable('@0');

        Http::fake([
            '*/v1/accounts/*/transactions*' => Http::response(['data' => []]),
        ]);

        $result = $this->client->findIncomingTransactionByAddress($address, CryptoAsset::TRX, $since);

        $this->assertNull($result);
    }

    public function test_trx_returns_null_on_api_error(): void
    {
        $address = CryptoAddress::fromString(self::TRON_ADDRESS);
        $since   = new DateTimeImmutable('@0');

        Http::fake([
            '*/v1/accounts/*/transactions*' => Http::response([], 500),
        ]);

        $result = $this->client->findIncomingTransactionByAddress($address, CryptoAsset::TRX, $since);

        $this->assertNull($result);
    }

    public function test_trx_ignores_failed_transactions(): void
    {
        $address = CryptoAddress::fromString(self::TRON_ADDRESS);
        $since   = new DateTimeImmutable('@0');

        $tx = $this->makeTrxTx(hash: self::TX_HASH, amount: 5_000_000, timestampMs: 1_000_000);
        $tx['ret'][0]['contractRet'] = 'REVERT';

        Http::fake([
            '*/v1/accounts/*/transactions*' => Http::response(['data' => [$tx]]),
        ]);

        $result = $this->client->findIncomingTransactionByAddress($address, CryptoAsset::TRX, $since);

        $this->assertNull($result);
    }

    public function test_trx_ignores_zero_amount_transactions(): void
    {
        $address = CryptoAddress::fromString(self::TRON_ADDRESS);
        $since   = new DateTimeImmutable('@0');

        Http::fake([
            '*/v1/accounts/*/transactions*' => Http::response([
                'data' => [
                    $this->makeTrxTx(hash: self::TX_HASH, amount: 0, timestampMs: 1_000_000),
                ],
            ]),
        ]);

        $result = $this->client->findIncomingTransactionByAddress($address, CryptoAsset::TRX, $since);

        $this->assertNull($result);
    }

    // ─── USDT-TRC20 ──────────────────────────────────────────────────────────

    public function test_find_usdt_trc20_transaction_for_address(): void
    {
        $address = CryptoAddress::fromString(self::TRON_ADDRESS);
        $since   = new DateTimeImmutable('@0');

        Http::fake([
            '*/v1/accounts/*/transactions/trc20*' => Http::response([
                'data' => [
                    $this->makeUsdtTrc20Transfer(
                        txId: self::TX_HASH,
                        amount: '1000000',
                        timestampMs: 2_000_000,
                    ),
                ],
            ]),
        ]);

        $result = $this->client->findIncomingTransactionByAddress($address, CryptoAsset::USDT_TRC20, $since);

        $this->assertNotNull($result);
        $this->assertSame(self::TX_HASH, $result->hash->toString());
        $this->assertSame(1_000_000, $result->actualAmount->units());
        $this->assertSame(CryptoAsset::USDT_TRC20, $result->actualAmount->asset());
    }

    public function test_usdt_trc20_returns_null_on_api_error(): void
    {
        $address = CryptoAddress::fromString(self::TRON_ADDRESS);
        $since   = new DateTimeImmutable('@0');

        Http::fake([
            '*/v1/accounts/*/transactions/trc20*' => Http::response([], 503),
        ]);

        $result = $this->client->findIncomingTransactionByAddress($address, CryptoAsset::USDT_TRC20, $since);

        $this->assertNull($result);
    }

    public function test_usdt_trc20_ignores_zero_amount(): void
    {
        $address = CryptoAddress::fromString(self::TRON_ADDRESS);
        $since   = new DateTimeImmutable('@0');

        Http::fake([
            '*/v1/accounts/*/transactions/trc20*' => Http::response([
                'data' => [
                    $this->makeUsdtTrc20Transfer(
                        txId: self::TX_HASH,
                        amount: '0',
                        timestampMs: 2_000_000,
                    ),
                ],
            ]),
        ]);

        $result = $this->client->findIncomingTransactionByAddress($address, CryptoAsset::USDT_TRC20, $since);

        $this->assertNull($result);
    }

    public function test_usdt_trc20_ignores_old_transfers(): void
    {
        $address = CryptoAddress::fromString(self::TRON_ADDRESS);
        // since is in the future relative to the transfer
        $since = new DateTimeImmutable('@9999999999');

        Http::fake([
            '*/v1/accounts/*/transactions/trc20*' => Http::response([
                'data' => [
                    $this->makeUsdtTrc20Transfer(
                        txId: self::TX_HASH,
                        amount: '1000000',
                        timestampMs: 1_000,
                    ),
                ],
            ]),
        ]);

        $result = $this->client->findIncomingTransactionByAddress($address, CryptoAsset::USDT_TRC20, $since);

        $this->assertNull($result);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    /**
     * @return array<string, mixed>
     */
    private function makeTrxTx(string $hash, int $amount, int $timestampMs): array
    {
        return [
            'txID' => $hash,
            'ret'  => [['contractRet' => 'SUCCESS']],
            'raw_data' => [
                'timestamp' => $timestampMs,
                'contract'  => [
                    [
                        'type'      => 'TransferContract',
                        'parameter' => [
                            'value' => [
                                'amount'     => $amount,
                                'to_address' => '41' . str_repeat('0', 40),
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function makeUsdtTrc20Transfer(string $txId, string $amount, int $timestampMs): array
    {
        return [
            'transaction_id'  => $txId,
            'value'           => $amount,
            'to'              => self::TRON_ADDRESS,
            'block_timestamp' => $timestampMs,
        ];
    }
}
