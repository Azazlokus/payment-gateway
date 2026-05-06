<?php

declare(strict_types=1);

namespace Tests\Unit\CryptoPayments;

use App\CryptoPayments\Domain\Contracts\BlockchainClientInterface;
use App\CryptoPayments\Domain\Enums\CryptoAsset;
use App\CryptoPayments\Domain\Enums\DepositMode;
use App\CryptoPayments\Domain\ValueObjects\CryptoAddress;
use App\CryptoPayments\Domain\ValueObjects\NativeCryptoAmount;
use App\CryptoPayments\Infrastructure\Blockchain\BitcoinBlockchainClient;
use App\CryptoPayments\Infrastructure\Blockchain\TonBlockchainClient;
use App\CryptoPayments\Infrastructure\Blockchain\TronBlockchainClient;
use App\Payments\Infrastructure\Observability\PaymentLogger;
use Mockery;
use Tests\TestCase;

/**
 * Contract tests: every BlockchainClientInterface implementation must
 * satisfy the same behavioural contract regardless of network.
 */
class BlockchainClientContractTest extends TestCase
{
    /** @return array<string, array{BlockchainClientInterface}> */
    public static function clientDataset(): array
    {
        $logger = Mockery::mock(PaymentLogger::class);
        $logger->shouldReceive('info', 'warning', 'error')->andReturnNull()->byDefault();

        return [
            'ton' => [
                new TonBlockchainClient(
                    masterAddress: 'UQAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAFy',
                    apiKey: '',
                    apiUrl: 'https://toncenter.com/api/v2',
                    apiV3Url: 'https://toncenter.com/api/v3',
                    usdtJettonMaster: 'EQCxE6mUtQJKFnGfaROTKOt1lZbDiiX1kCixRv7Nw2Id_sDs',
                    logger: $logger,
                ),
            ],
            'tron' => [
                new TronBlockchainClient(
                    apiUrl: 'https://api.trongrid.io',
                    apiKey: '',
                    usdtContract: 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t',
                    addressPool: ['TN1GK62RKZcVdFoTqVq9rCMmD5N5UxFMVo'],
                    logger: $logger,
                ),
            ],
            'bitcoin' => [
                new BitcoinBlockchainClient(
                    apiUrl: 'https://mempool.space/api',
                    addressPool: ['bc1qxy2kgdygjrsqtzq2n0yrf2493p83kkfjhx0wlh'],
                    logger: $logger,
                ),
            ],
        ];
    }

    /** @dataProvider clientDataset */
    public function test_implements_interface(BlockchainClientInterface $client): void
    {
        $this->assertInstanceOf(BlockchainClientInterface::class, $client);
    }

    /** @dataProvider clientDataset */
    public function test_network_returns_non_empty_string(BlockchainClientInterface $client): void
    {
        $this->assertNotEmpty($client->network());
        $this->assertIsString($client->network());
    }

    /** @dataProvider clientDataset */
    public function test_supported_assets_returns_non_empty_array(BlockchainClientInterface $client): void
    {
        $assets = $client->supportedAssets();
        $this->assertNotEmpty($assets);
        foreach ($assets as $asset) {
            $this->assertInstanceOf(CryptoAsset::class, $asset);
        }
    }

    /** @dataProvider clientDataset */
    public function test_deposit_mode_returns_valid_enum(BlockchainClientInterface $client): void
    {
        $mode = $client->depositMode();
        $this->assertInstanceOf(DepositMode::class, $mode);
    }

    /** @dataProvider clientDataset */
    public function test_master_address_returns_crypto_address_for_memo_mode(BlockchainClientInterface $client): void
    {
        if ($client->depositMode() !== DepositMode::Memo) {
            $this->markTestSkipped('Not memo-based');
        }

        $address = $client->masterDepositAddress();
        $this->assertInstanceOf(CryptoAddress::class, $address);
        $this->assertNotEmpty($address->toString());
    }

    /** @dataProvider clientDataset */
    public function test_address_pool_returns_array_for_unique_address_mode(BlockchainClientInterface $client): void
    {
        if ($client->depositMode() !== DepositMode::UniqueAddress) {
            $this->markTestSkipped('Not unique-address-based');
        }

        $pool = $client->depositAddressPool();
        $this->assertIsArray($pool);
        $this->assertNotEmpty($pool);
        foreach ($pool as $addr) {
            $this->assertIsString($addr);
            $this->assertNotEmpty($addr);
        }
    }

    /** @dataProvider clientDataset */
    public function test_can_send_returns_bool(BlockchainClientInterface $client): void
    {
        $this->assertIsBool($client->canSend());
    }

    /** @dataProvider clientDataset */
    public function test_send_transfer_throws_when_not_configured(BlockchainClientInterface $client): void
    {
        // All clients in the dataset have no hot wallet configured → must throw
        $this->expectException(\RuntimeException::class);

        $client->sendTransfer(
            to: CryptoAddress::fromString($client->depositMode() === DepositMode::Memo
                ? 'UQAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAFy'
                : ($client->depositAddressPool()[0] ?? 'some-address')),
            amount: NativeCryptoAmount::of(1, $client->supportedAssets()[0]),
            asset: $client->supportedAssets()[0],
            comment: 'test-refund',
        );
    }

    /** @dataProvider clientDataset */
    public function test_network_and_supported_assets_are_consistent(BlockchainClientInterface $client): void
    {
        $network = $client->network();
        foreach ($client->supportedAssets() as $asset) {
            $this->assertSame(
                $network,
                $asset->network(),
                "Asset {$asset->value} claims network '{$asset->network()}' but client says '{$network}'"
            );
        }
    }
}
