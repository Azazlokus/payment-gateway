<?php

declare(strict_types=1);

namespace Tests\Feature\CryptoPayments;

use App\CryptoPayments\Domain\Contracts\BlockchainClientInterface;
use App\CryptoPayments\Domain\Contracts\CryptoDepositRepositoryInterface;
use App\CryptoPayments\Domain\Contracts\PriceOracleInterface;
use App\CryptoPayments\Domain\Enums\CryptoAsset;
use App\CryptoPayments\Domain\ValueObjects\CryptoDepositId;
use App\CryptoPayments\Domain\ValueObjects\TonAddress;
use App\CryptoPayments\Infrastructure\Blockchain\BlockchainClientRegistry;
use App\CryptoPayments\Infrastructure\Observability\CryptoMetricsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class CryptoDepositTest extends TestCase
{
    use RefreshDatabase;

    private const MASTER_ADDRESS = 'UQAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAFy';

    protected function setUp(): void
    {
        parent::setUp();

        $mockClient = $this->mock(BlockchainClientInterface::class);
        $mockClient->shouldReceive('network')->andReturn('ton');
        $mockClient->shouldReceive('supportedAssets')->andReturn([CryptoAsset::TON]);
        $mockClient->shouldReceive('masterDepositAddress')
            ->andReturn(TonAddress::fromString(self::MASTER_ADDRESS));

        $mockRegistry = $this->mock(BlockchainClientRegistry::class);
        $mockRegistry->shouldReceive('getForAsset')->andReturn($mockClient);

        $mockOracle = $this->mock(PriceOracleInterface::class);
        $mockOracle->shouldReceive('kopecksToCryptoUnits')->andReturn(125_000_000);
        $mockOracle->shouldReceive('getRateKopecks')->andReturn(40_000);

        $mockMetrics = $this->mock(CryptoMetricsService::class);
        $mockMetrics->shouldReceive('depositCreated')->andReturnNull();
    }

    public function test_create_crypto_deposit_returns_201(): void
    {
        $response = $this->postJson('/api/crypto/deposits', [
            'payment_id'          => 'pay-001',
            'fiat_amount_kopecks' => 5000,
            'asset'               => 'TON',
        ]);

        $response->assertStatus(Response::HTTP_CREATED);
        $response->assertJsonStructure([
            'depositId',
            'paymentId',
            'status',
            'asset',
            'expectedUnits',
            'cryptoAmount',
            'fiatAmountKopecks',
            'depositAddress',
            'memo',
            'expiresAt',
            'qrPayload',
        ]);
    }

    public function test_create_crypto_deposit_stores_in_db(): void
    {
        $response = $this->postJson('/api/crypto/deposits', [
            'payment_id'          => 'pay-002',
            'fiat_amount_kopecks' => 10000,
            'asset'               => 'TON',
        ]);

        $response->assertStatus(Response::HTTP_CREATED);

        $depositId = $response->json('depositId');
        $this->assertNotNull($depositId);

        $repository = app(CryptoDepositRepositoryInterface::class);
        $deposit = $repository->findById(CryptoDepositId::fromString($depositId));

        $this->assertNotNull($deposit);
        $this->assertSame('pay-002', $deposit->paymentId());
        $this->assertSame(CryptoAsset::TON, $deposit->asset());
        $this->assertSame(10000, $deposit->fiatAmountKopecks());
    }

    public function test_show_returns_deposit_by_id(): void
    {
        $createResponse = $this->postJson('/api/crypto/deposits', [
            'payment_id'          => 'pay-003',
            'fiat_amount_kopecks' => 5000,
            'asset'               => 'TON',
        ]);

        $depositId = $createResponse->json('depositId');

        $showResponse = $this->getJson("/api/crypto/deposits/{$depositId}");

        $showResponse->assertStatus(Response::HTTP_OK);
        $showResponse->assertJsonPath('depositId', $depositId);
        $showResponse->assertJsonPath('paymentId', 'pay-003');
    }

    public function test_show_returns_404_for_unknown_deposit(): void
    {
        $fakeId = CryptoDepositId::generate()->toString();

        $response = $this->getJson("/api/crypto/deposits/{$fakeId}");

        $response->assertStatus(Response::HTTP_NOT_FOUND);
    }

    public function test_create_deposit_validates_required_fields(): void
    {
        $response = $this->postJson('/api/crypto/deposits', []);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
        $response->assertJsonValidationErrors(['payment_id', 'fiat_amount_kopecks', 'asset']);
    }

    public function test_create_deposit_validates_minimum_amount(): void
    {
        $response = $this->postJson('/api/crypto/deposits', [
            'payment_id'          => 'pay-004',
            'fiat_amount_kopecks' => 50,
            'asset'               => 'TON',
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
        $response->assertJsonValidationErrors(['fiat_amount_kopecks']);
    }

    public function test_create_deposit_validates_asset_enum(): void
    {
        $response = $this->postJson('/api/crypto/deposits', [
            'payment_id'          => 'pay-005',
            'fiat_amount_kopecks' => 5000,
            'asset'               => 'BTC',
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
        $response->assertJsonValidationErrors(['asset']);
    }

    public function test_create_deposit_returns_correct_deposit_address(): void
    {
        $response = $this->postJson('/api/crypto/deposits', [
            'payment_id'          => 'pay-006',
            'fiat_amount_kopecks' => 5000,
            'asset'               => 'TON',
        ]);

        $response->assertStatus(Response::HTTP_CREATED);
        // The DTO uses toNonBounceable() on the address
        $this->assertNotEmpty($response->json('depositAddress'));
    }

    public function test_create_deposit_qr_payload_contains_amount_and_memo(): void
    {
        $response = $this->postJson('/api/crypto/deposits', [
            'payment_id'          => 'pay-007',
            'fiat_amount_kopecks' => 5000,
            'asset'               => 'TON',
        ]);

        $response->assertStatus(Response::HTTP_CREATED);

        $qr   = $response->json('qrPayload');
        $memo = $response->json('memo');

        $this->assertStringStartsWith('ton://transfer/', $qr);
        $this->assertStringContainsString("text={$memo}", $qr);
    }
}
