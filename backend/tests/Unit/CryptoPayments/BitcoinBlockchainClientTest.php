<?php

declare(strict_types=1);

namespace Tests\Unit\CryptoPayments;

use App\CryptoPayments\Domain\Enums\CryptoAsset;
use App\CryptoPayments\Domain\ValueObjects\CryptoAddress;
use App\CryptoPayments\Infrastructure\Blockchain\BitcoinBlockchainClient;
use App\Payments\Infrastructure\Observability\PaymentLogger;
use DateTimeImmutable;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

/**
 * Unit tests for BitcoinBlockchainClient.
 *
 * All HTTP calls are intercepted via Http::fake() — no real network requests.
 */
class BitcoinBlockchainClientTest extends TestCase
{
    private const BTC_ADDRESS = 'bc1qxy2kgdygjrsqtzq2n0yrf2493p83kkfjhx0wlh';

    private const TX_ID = 'a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2';

    private BitcoinBlockchainClient $client;

    protected function setUp(): void
    {
        parent::setUp();

        $logger = Mockery::mock(PaymentLogger::class);
        $logger->shouldReceive('warning')->byDefault();

        $this->client = new BitcoinBlockchainClient(
            apiUrl: 'https://mempool.space.test/api',
            addressPool: [self::BTC_ADDRESS],
            logger: $logger,
        );
    }

    public function test_find_confirmed_transaction_for_address(): void
    {
        $address = CryptoAddress::fromString(self::BTC_ADDRESS);
        $since = new DateTimeImmutable('@0');

        Http::fake([
            '*/address/*/txs*' => Http::response([
                $this->makeBtcTx(
                    txid: self::TX_ID,
                    confirmed: true,
                    blockTime: 1_700_000_000,
                    address: self::BTC_ADDRESS,
                    value: 100_000,
                ),
            ]),
        ]);

        $result = $this->client->findIncomingTransactionByAddress($address, CryptoAsset::BTC, $since);

        $this->assertNotNull($result);
        $this->assertSame(self::TX_ID, $result->hash->toString());
        $this->assertSame(100_000, $result->actualAmount->units());
        $this->assertSame(CryptoAsset::BTC, $result->actualAmount->asset());
    }

    public function test_unconfirmed_transaction_is_ignored(): void
    {
        $address = CryptoAddress::fromString(self::BTC_ADDRESS);
        $since = new DateTimeImmutable('@0');

        Http::fake([
            '*/address/*/txs*' => Http::response([
                $this->makeBtcTx(
                    txid: self::TX_ID,
                    confirmed: false,
                    blockTime: 0,
                    address: self::BTC_ADDRESS,
                    value: 100_000,
                ),
            ]),
        ]);

        $result = $this->client->findIncomingTransactionByAddress($address, CryptoAsset::BTC, $since);

        $this->assertNull($result);
    }

    public function test_old_transaction_before_since_is_ignored(): void
    {
        $address = CryptoAddress::fromString(self::BTC_ADDRESS);
        $since = new DateTimeImmutable('@9999999999');

        Http::fake([
            '*/address/*/txs*' => Http::response([
                $this->makeBtcTx(
                    txid: self::TX_ID,
                    confirmed: true,
                    blockTime: 1_000,
                    address: self::BTC_ADDRESS,
                    value: 100_000,
                ),
            ]),
        ]);

        $result = $this->client->findIncomingTransactionByAddress($address, CryptoAsset::BTC, $since);

        $this->assertNull($result);
    }

    public function test_api_error_returns_null(): void
    {
        $address = CryptoAddress::fromString(self::BTC_ADDRESS);
        $since = new DateTimeImmutable('@0');

        Http::fake([
            '*/address/*/txs*' => Http::response([], 500),
        ]);

        $result = $this->client->findIncomingTransactionByAddress($address, CryptoAsset::BTC, $since);

        $this->assertNull($result);
    }

    public function test_transaction_with_zero_value_for_address_is_ignored(): void
    {
        $address = CryptoAddress::fromString(self::BTC_ADDRESS);
        $since = new DateTimeImmutable('@0');

        // The tx is confirmed and recent but all vout go to a different address
        Http::fake([
            '*/address/*/txs*' => Http::response([
                $this->makeBtcTx(
                    txid: self::TX_ID,
                    confirmed: true,
                    blockTime: 1_700_000_000,
                    address: 'bc1q_other_address',
                    value: 100_000,
                ),
            ]),
        ]);

        $result = $this->client->findIncomingTransactionByAddress($address, CryptoAsset::BTC, $since);

        $this->assertNull($result);
    }

    public function test_accumulates_multiple_vout_to_same_address(): void
    {
        $address = CryptoAddress::fromString(self::BTC_ADDRESS);
        $since = new DateTimeImmutable('@0');

        $tx = [
            'txid' => self::TX_ID,
            'status' => ['confirmed' => true, 'block_time' => 1_700_000_000],
            'vout' => [
                ['scriptpubkey_address' => self::BTC_ADDRESS, 'value' => 60_000],
                ['scriptpubkey_address' => 'bc1q_other', 'value' => 40_000],
                ['scriptpubkey_address' => self::BTC_ADDRESS, 'value' => 40_000],
            ],
        ];

        Http::fake([
            '*/address/*/txs*' => Http::response([$tx]),
        ]);

        $result = $this->client->findIncomingTransactionByAddress($address, CryptoAsset::BTC, $since);

        $this->assertNotNull($result);
        $this->assertSame(100_000, $result->actualAmount->units());
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    /**
     * @return array<string, mixed>
     */
    private function makeBtcTx(
        string $txid,
        bool $confirmed,
        int $blockTime,
        string $address,
        int $value,
    ): array {
        return [
            'txid' => $txid,
            'status' => [
                'confirmed' => $confirmed,
                'block_time' => $blockTime,
            ],
            'vout' => [
                [
                    'scriptpubkey_address' => $address,
                    'value' => $value,
                ],
            ],
        ];
    }
}
