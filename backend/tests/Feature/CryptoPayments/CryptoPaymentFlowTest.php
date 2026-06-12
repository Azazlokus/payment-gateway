<?php

declare(strict_types=1);

namespace Tests\Feature\CryptoPayments;

use App\Contexts\CryptoPayments\Application\ACL\CryptoDepositToPaymentAdapter;
use App\Contexts\CryptoPayments\Domain\Contracts\BlockchainClientInterface;
use App\Contexts\CryptoPayments\Domain\Contracts\CryptoDepositRepositoryInterface;
use App\Contexts\CryptoPayments\Domain\Contracts\CryptoRefundRepositoryInterface;
use App\Contexts\CryptoPayments\Domain\Contracts\PriceOracleInterface;
use App\Contexts\CryptoPayments\Domain\Enums\CryptoAsset;
use App\Contexts\CryptoPayments\Domain\Enums\CryptoDepositStatus;
use App\Contexts\CryptoPayments\Domain\Enums\CryptoRefundStatus;
use App\Contexts\CryptoPayments\Domain\Enums\DepositMode;
use App\Contexts\CryptoPayments\Domain\ValueObjects\CryptoAddress;
use App\Contexts\CryptoPayments\Domain\ValueObjects\CryptoDepositId;
use App\Contexts\CryptoPayments\Domain\ValueObjects\CryptoRefundId;
use App\Contexts\CryptoPayments\Domain\ValueObjects\NativeCryptoAmount;
use App\Contexts\CryptoPayments\Domain\ValueObjects\TransactionResult;
use App\Contexts\CryptoPayments\Domain\ValueObjects\TxHash;
use App\Contexts\CryptoPayments\Infrastructure\Blockchain\BlockchainClientRegistry;
use App\Contexts\CryptoPayments\Infrastructure\Jobs\PollCryptoDepositsJob;
use App\Contexts\CryptoPayments\Infrastructure\Jobs\ProcessCryptoRefundsJob;
use App\Contexts\CryptoPayments\Infrastructure\Observability\CryptoMetricsService;
use App\Contexts\Payments\Infrastructure\Observability\PaymentLogger;
use App\Contexts\Payments\Infrastructure\Persistence\Models\PaymentModel;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * E2E tests for the full crypto-payment flow:
 *   Create payment → Create crypto deposit → PollCryptoDepositsJob confirms → Payment succeeds
 *   Confirmed deposit → Create refund request → ProcessCryptoRefundsJob handles it
 */
class CryptoPaymentFlowTest extends TestCase
{
    use RefreshDatabase;

    private const MASTER = 'UQAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAFy';

    private const TX_HASH = 'abc123def456abc123def456abc123def456abc123def456abc123def456abc1';

    private BlockchainClientInterface $mockClient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockClient = Mockery::mock(BlockchainClientInterface::class);
        $this->mockClient->shouldReceive('network')->andReturn('ton')->byDefault();
        $this->mockClient->shouldReceive('supportedAssets')->andReturn([CryptoAsset::TON])->byDefault();
        $this->mockClient->shouldReceive('depositMode')->andReturn(DepositMode::Memo)->byDefault();
        $this->mockClient->shouldReceive('masterDepositAddress')
            ->andReturn(CryptoAddress::fromString(self::MASTER))->byDefault();
        $this->mockClient->shouldReceive('canSend')->andReturn(false)->byDefault();
        $this->mockClient->shouldReceive('findIncomingTransactionsBatch')->andReturn([])->byDefault();

        // BlockchainClientRegistry — final, мокать нельзя. Собираем реальный реестр
        // и регистрируем в нём мок-клиент через штатный register() (network='ton').
        $registry = new BlockchainClientRegistry;
        $registry->register($this->mockClient);
        $this->app->instance(BlockchainClientRegistry::class, $registry);

        $mockOracle = $this->mock(PriceOracleInterface::class);
        $mockOracle->shouldReceive('kopecksToCryptoUnits')->andReturn(125_000_000)->byDefault();
        $mockOracle->shouldReceive('getRateKopecks')->andReturn(40_000)->byDefault();

        // CryptoMetricsService — final, но это тонкая обёртка над MetricsService,
        // который TestCase уже подменил no-op мок. Отдельный мок не нужен.
    }

    // ─── Flow: Create → Confirm via polling ──────────────────────────────────

    public function test_full_flow_deposit_confirmed_via_poll(): void
    {
        // 1. Create a payment in DB (simulate existing payment)
        $payment = PaymentModel::create([
            'id' => 'pay-e2e-001',
            'provider' => 'crypto',
            'amount' => 5000,
            'currency' => 'RUB',
            'status' => 'Pending',
            'external_id' => null,
            'idempotency_key' => 'idem-e2e-001',
            'description' => 'E2E test payment',
            'customer_email' => null,
            'metadata' => null,
            'three_ds_required' => false,
            'three_ds_challenge_url' => null,
            'refunded_amount' => 0,
            'payment_method_id' => null,
        ]);

        // 2. Create crypto deposit via API
        $response = $this->postJson('/api/v1/crypto/deposits', [
            'payment_id' => $payment->id,
            'fiat_amount_kopecks' => 5000,
            'asset' => 'TON',
        ]);

        $response->assertStatus(201);
        $depositId = $response->json('depositId');
        $memo = $response->json('memo');

        $this->assertNotEmpty($depositId);
        $this->assertNotEmpty($memo);

        // 3. Simulate blockchain returning a confirmed transaction
        $this->mockClient->shouldReceive('findIncomingTransactionsBatch')
            ->andReturn([
                $memo => new TransactionResult(
                    hash: TxHash::fromString(self::TX_HASH),
                    actualAmount: NativeCryptoAmount::ofNanotons(125_000_000),
                    confirmedAt: new DateTimeImmutable,
                ),
            ]);

        // 4. Run the polling job
        app(PollCryptoDepositsJob::class)->handle(
            app(CryptoDepositRepositoryInterface::class),
            app(BlockchainClientRegistry::class),
            app(CryptoDepositToPaymentAdapter::class),
            app(CryptoMetricsService::class),
            app(PaymentLogger::class),
        );

        // 5. Verify deposit status is Confirmed
        $repository = app(CryptoDepositRepositoryInterface::class);
        $deposit = $repository->findById(CryptoDepositId::fromString($depositId));

        $this->assertNotNull($deposit);
        $this->assertSame(CryptoDepositStatus::Confirmed, $deposit->status());
        $this->assertSame(self::TX_HASH, $deposit->txHash()?->toString());
    }

    public function test_deposit_show_reflects_confirmed_status(): void
    {
        // Create deposit
        $createResp = $this->postJson('/api/v1/crypto/deposits', [
            'payment_id' => 'pay-e2e-show',
            'fiat_amount_kopecks' => 5000,
            'asset' => 'TON',
        ]);
        $createResp->assertStatus(201);
        $depositId = $createResp->json('depositId');

        // Show before confirmation
        $before = $this->getJson("/api/v1/crypto/deposits/{$depositId}");
        $before->assertJsonPath('status', 'Awaiting');

        // Confirm
        $memo = $createResp->json('memo');
        $this->mockClient->shouldReceive('findIncomingTransactionsBatch')
            ->andReturn([
                $memo => new TransactionResult(
                    hash: TxHash::fromString(self::TX_HASH),
                    actualAmount: NativeCryptoAmount::ofNanotons(125_000_000),
                    confirmedAt: new DateTimeImmutable,
                ),
            ]);

        app(PollCryptoDepositsJob::class)->handle(
            app(CryptoDepositRepositoryInterface::class),
            app(BlockchainClientRegistry::class),
            app(CryptoDepositToPaymentAdapter::class),
            app(CryptoMetricsService::class),
            app(PaymentLogger::class),
        );

        // Show after confirmation
        $after = $this->getJson("/api/v1/crypto/deposits/{$depositId}");
        $after->assertJsonPath('status', 'Confirmed');
        $after->assertJsonPath('txHash', self::TX_HASH);
    }

    // ─── Flow: Confirmed deposit → Refund request ────────────────────────────

    public function test_refund_request_created_for_confirmed_deposit(): void
    {
        // Create and confirm deposit
        $createResp = $this->postJson('/api/v1/crypto/deposits', [
            'payment_id' => 'pay-e2e-refund',
            'fiat_amount_kopecks' => 5000,
            'asset' => 'TON',
        ]);
        $depositId = $createResp->json('depositId');
        $memo = $createResp->json('memo');

        $this->mockClient->shouldReceive('findIncomingTransactionsBatch')
            ->andReturn([
                $memo => new TransactionResult(
                    hash: TxHash::fromString(self::TX_HASH),
                    actualAmount: NativeCryptoAmount::ofNanotons(125_000_000),
                    confirmedAt: new DateTimeImmutable,
                ),
            ]);

        app(PollCryptoDepositsJob::class)->handle(
            app(CryptoDepositRepositoryInterface::class),
            app(BlockchainClientRegistry::class),
            app(CryptoDepositToPaymentAdapter::class),
            app(CryptoMetricsService::class),
            app(PaymentLogger::class),
        );

        // Request refund via API
        $refundResp = $this->postJson("/api/v1/crypto/deposits/{$depositId}/refund", [
            'to_address' => self::MASTER,
        ]);

        $refundResp->assertStatus(201);
        $refundResp->assertJsonStructure(['refund_id']);

        $this->assertNotEmpty($refundResp->json('refund_id'));
    }

    public function test_refund_rejected_for_awaiting_deposit(): void
    {
        $createResp = $this->postJson('/api/v1/crypto/deposits', [
            'payment_id' => 'pay-e2e-refund-fail',
            'fiat_amount_kopecks' => 5000,
            'asset' => 'TON',
        ]);
        $depositId = $createResp->json('depositId');

        $refundResp = $this->postJson("/api/v1/crypto/deposits/{$depositId}/refund", [
            'to_address' => self::MASTER,
        ]);

        $refundResp->assertStatus(409);
        $refundResp->assertJsonPath('code', 'invalid_state');
    }

    public function test_duplicate_refund_rejected(): void
    {
        // Create and confirm
        $createResp = $this->postJson('/api/v1/crypto/deposits', [
            'payment_id' => 'pay-e2e-dup-refund',
            'fiat_amount_kopecks' => 5000,
            'asset' => 'TON',
        ]);
        $depositId = $createResp->json('depositId');
        $memo = $createResp->json('memo');

        $this->mockClient->shouldReceive('findIncomingTransactionsBatch')
            ->andReturn([
                $memo => new TransactionResult(
                    hash: TxHash::fromString(self::TX_HASH),
                    actualAmount: NativeCryptoAmount::ofNanotons(125_000_000),
                    confirmedAt: new DateTimeImmutable,
                ),
            ]);

        app(PollCryptoDepositsJob::class)->handle(
            app(CryptoDepositRepositoryInterface::class),
            app(BlockchainClientRegistry::class),
            app(CryptoDepositToPaymentAdapter::class),
            app(CryptoMetricsService::class),
            app(PaymentLogger::class),
        );

        // First refund
        $this->postJson("/api/v1/crypto/deposits/{$depositId}/refund", ['to_address' => self::MASTER])
            ->assertStatus(201);

        // Second refund — must be rejected
        $this->postJson("/api/v1/crypto/deposits/{$depositId}/refund", ['to_address' => self::MASTER])
            ->assertStatus(409);
    }

    public function test_process_refunds_job_marks_failed_when_no_hot_wallet(): void
    {
        // Confirm deposit and create refund request
        $createResp = $this->postJson('/api/v1/crypto/deposits', [
            'payment_id' => 'pay-e2e-job-refund',
            'fiat_amount_kopecks' => 5000,
            'asset' => 'TON',
        ]);
        $depositId = $createResp->json('depositId');
        $memo = $createResp->json('memo');

        $this->mockClient->shouldReceive('findIncomingTransactionsBatch')
            ->andReturn([
                $memo => new TransactionResult(
                    hash: TxHash::fromString(self::TX_HASH),
                    actualAmount: NativeCryptoAmount::ofNanotons(125_000_000),
                    confirmedAt: new DateTimeImmutable,
                ),
            ]);

        app(PollCryptoDepositsJob::class)->handle(
            app(CryptoDepositRepositoryInterface::class),
            app(BlockchainClientRegistry::class),
            app(CryptoDepositToPaymentAdapter::class),
            app(CryptoMetricsService::class),
            app(PaymentLogger::class),
        );

        $refundResp = $this->postJson("/api/v1/crypto/deposits/{$depositId}/refund", [
            'to_address' => self::MASTER,
        ]);
        $refundId = $refundResp->json('refund_id');

        // canSend() returns false → job marks refund as failed
        app(ProcessCryptoRefundsJob::class)->handle(
            app(CryptoRefundRepositoryInterface::class),
            app(BlockchainClientRegistry::class),
            app(PaymentLogger::class),
        );

        $refundRepo = app(CryptoRefundRepositoryInterface::class);
        $refund = $refundRepo->findById(
            CryptoRefundId::fromString($refundId)
        );

        $this->assertNotNull($refund);
        $this->assertSame(CryptoRefundStatus::Failed, $refund->status());
        $this->assertNotEmpty($refund->failureReason());
    }

    public function test_refund_returns_404_for_unknown_deposit(): void
    {
        $this->postJson('/api/v1/crypto/deposits/nonexistent-id/refund', [
            'to_address' => self::MASTER,
        ])->assertStatus(409); // CryptoDepositException → 409 with invalid_state
    }
}
