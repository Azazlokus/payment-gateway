<?php

declare(strict_types=1);

namespace Tests\Feature\CryptoPayments;

use App\Contexts\CryptoPayments\Domain\Contracts\BlockchainClientInterface;
use App\Contexts\CryptoPayments\Domain\Contracts\CryptoDepositRepositoryInterface;
use App\Contexts\CryptoPayments\Domain\Contracts\PriceOracleInterface;
use App\Contexts\CryptoPayments\Domain\Enums\CryptoAsset;
use App\Contexts\CryptoPayments\Domain\Enums\DepositMode;
use App\Contexts\CryptoPayments\Domain\ValueObjects\CryptoAddress;
use App\Contexts\CryptoPayments\Domain\ValueObjects\CryptoDepositId;
use App\Contexts\CryptoPayments\Infrastructure\Blockchain\BlockchainClientRegistry;
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
        $mockClient->shouldReceive('depositMode')->andReturn(DepositMode::Memo);
        $mockClient->shouldReceive('masterDepositAddress')
            ->andReturn(CryptoAddress::fromString(self::MASTER_ADDRESS));

        // BlockchainClientRegistry — final (как и Payment), мокать его нельзя.
        // Вместо этого собираем реальный реестр и регистрируем в нём мок-клиент:
        // register() — штатный шов для подмены клиентов в тестах.
        $registry = new BlockchainClientRegistry;
        $registry->register($mockClient);
        $this->app->instance(BlockchainClientRegistry::class, $registry);

        $mockOracle = $this->mock(PriceOracleInterface::class);
        $mockOracle->shouldReceive('kopecksToCryptoUnits')->andReturn(125_000_000);
        $mockOracle->shouldReceive('getRateKopecks')->andReturn(40_000);

        // CryptoMetricsService — final, но это лишь тонкая обёртка над MetricsService,
        // который TestCase уже подменил no-op мок. Поэтому мокать её не нужно —
        // реальная обёртка просто делегирует в no-op метрики.
    }

    public function test_create_crypto_deposit_returns_201(): void
    {
        $response = $this->postJson('/api/v1/crypto/deposits', [
            'payment_id' => 'pay-001',
            'fiat_amount_kopecks' => 5000,
            'asset' => 'TON',
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
        $response = $this->postJson('/api/v1/crypto/deposits', [
            'payment_id' => 'pay-002',
            'fiat_amount_kopecks' => 10000,
            'asset' => 'TON',
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
        $createResponse = $this->postJson('/api/v1/crypto/deposits', [
            'payment_id' => 'pay-003',
            'fiat_amount_kopecks' => 5000,
            'asset' => 'TON',
        ]);

        $depositId = $createResponse->json('depositId');

        $showResponse = $this->getJson("/api/v1/crypto/deposits/{$depositId}");

        $showResponse->assertStatus(Response::HTTP_OK);
        $showResponse->assertJsonPath('depositId', $depositId);
        $showResponse->assertJsonPath('paymentId', 'pay-003');
    }

    public function test_show_returns_404_for_unknown_deposit(): void
    {
        $fakeId = CryptoDepositId::generate()->toString();

        $response = $this->getJson("/api/v1/crypto/deposits/{$fakeId}");

        $response->assertStatus(Response::HTTP_NOT_FOUND);
    }

    public function test_create_deposit_validates_required_fields(): void
    {
        $response = $this->postJson('/api/v1/crypto/deposits', []);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
        $response->assertJsonValidationErrors(['payment_id', 'fiat_amount_kopecks', 'asset']);
    }

    public function test_create_deposit_validates_minimum_amount(): void
    {
        $response = $this->postJson('/api/v1/crypto/deposits', [
            'payment_id' => 'pay-004',
            'fiat_amount_kopecks' => 50,
            'asset' => 'TON',
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
        $response->assertJsonValidationErrors(['fiat_amount_kopecks']);
    }

    public function test_create_deposit_validates_asset_enum(): void
    {
        $response = $this->postJson('/api/v1/crypto/deposits', [
            'payment_id' => 'pay-005',
            'fiat_amount_kopecks' => 5000,
            'asset' => 'INVALID_ASSET',
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
        $response->assertJsonValidationErrors(['asset']);
    }

    public function test_create_deposit_returns_correct_deposit_address(): void
    {
        $response = $this->postJson('/api/v1/crypto/deposits', [
            'payment_id' => 'pay-006',
            'fiat_amount_kopecks' => 5000,
            'asset' => 'TON',
        ]);

        $response->assertStatus(Response::HTTP_CREATED);
        // The DTO uses toNonBounceable() on the address
        $this->assertNotEmpty($response->json('depositAddress'));
    }

    public function test_create_deposit_qr_payload_contains_amount_and_memo(): void
    {
        $response = $this->postJson('/api/v1/crypto/deposits', [
            'payment_id' => 'pay-007',
            'fiat_amount_kopecks' => 5000,
            'asset' => 'TON',
        ]);

        $response->assertStatus(Response::HTTP_CREATED);

        $qr = $response->json('qrPayload');
        $memo = $response->json('memo');

        $this->assertStringStartsWith('ton://transfer/', $qr);
        $this->assertStringContainsString('amount=', $qr);
        $this->assertStringContainsString("text={$memo}", $qr);
    }

    // ─── USDT-TON ────────────────────────────────────────────────────────────

    public function test_create_usdt_deposit_returns_201(): void
    {
        $response = $this->postJson('/api/v1/crypto/deposits', [
            'payment_id' => 'pay-usdt-001',
            'fiat_amount_kopecks' => 10000,
            'asset' => 'USDT_TON',
        ]);

        $response->assertStatus(Response::HTTP_CREATED);
        $response->assertJsonStructure([
            'depositId', 'paymentId', 'status', 'asset',
            'expectedUnits', 'cryptoAmount', 'fiatAmountKopecks',
            'depositAddress', 'memo', 'expiresAt', 'qrPayload',
        ]);
    }

    public function test_create_usdt_deposit_stores_correct_asset(): void
    {
        $response = $this->postJson('/api/v1/crypto/deposits', [
            'payment_id' => 'pay-usdt-002',
            'fiat_amount_kopecks' => 10000,
            'asset' => 'USDT_TON',
        ]);

        $response->assertStatus(Response::HTTP_CREATED);
        $this->assertSame('USDT_TON', $response->json('asset'));

        $repository = app(CryptoDepositRepositoryInterface::class);
        $deposit = $repository->findById(CryptoDepositId::fromString($response->json('depositId')));

        $this->assertNotNull($deposit);
        $this->assertSame(CryptoAsset::USDT_TON, $deposit->asset());
        $this->assertSame('pay-usdt-002', $deposit->paymentId());
    }

    public function test_create_usdt_deposit_qr_payload_has_no_amount_param(): void
    {
        // For USDT-TON the ton:// deep-link must NOT include ?amount= because
        // the amount is in micro-USDT (not nanotons) and wallets use Jetton transfer,
        // not a native TON transfer.
        $response = $this->postJson('/api/v1/crypto/deposits', [
            'payment_id' => 'pay-usdt-003',
            'fiat_amount_kopecks' => 10000,
            'asset' => 'USDT_TON',
        ]);

        $response->assertStatus(Response::HTTP_CREATED);

        $qr = $response->json('qrPayload');
        $memo = $response->json('memo');

        $this->assertStringStartsWith('ton://transfer/', $qr);
        $this->assertStringNotContainsString('amount=', $qr);
        $this->assertStringContainsString("text={$memo}", $qr);
    }

    public function test_create_usdt_deposit_human_readable_amount_has_six_decimals(): void
    {
        // Oracle mock returns 125_000_000 units; for USDT (6 decimals) → "125.000000"
        $response = $this->postJson('/api/v1/crypto/deposits', [
            'payment_id' => 'pay-usdt-004',
            'fiat_amount_kopecks' => 10000,
            'asset' => 'USDT_TON',
        ]);

        $response->assertStatus(Response::HTTP_CREATED);

        $cryptoAmount = $response->json('cryptoAmount');
        // Must have 6 decimal places (USDT decimals)
        $this->assertMatchesRegularExpression('/^\d+\.\d{6}$/', $cryptoAmount);
    }

    public function test_show_usdt_deposit_by_id(): void
    {
        $createResponse = $this->postJson('/api/v1/crypto/deposits', [
            'payment_id' => 'pay-usdt-005',
            'fiat_amount_kopecks' => 5000,
            'asset' => 'USDT_TON',
        ]);
        $createResponse->assertStatus(Response::HTTP_CREATED);

        $depositId = $createResponse->json('depositId');

        $showResponse = $this->getJson("/api/v1/crypto/deposits/{$depositId}");

        $showResponse->assertStatus(Response::HTTP_OK);
        $showResponse->assertJsonPath('depositId', $depositId);
        $showResponse->assertJsonPath('asset', 'USDT_TON');
    }
}
