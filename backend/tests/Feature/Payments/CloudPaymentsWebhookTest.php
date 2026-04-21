<?php

declare(strict_types=1);

namespace Tests\Feature\Payments;

use App\Payments\Domain\Contracts\PaymentRepositoryInterface;
use App\Payments\Domain\ValueObjects\PaymentId;
use App\Payments\Infrastructure\Jobs\ProcessCloudPaymentsWebhookJob;
use App\Payments\Infrastructure\Observability\MetricsService;
use App\Payments\Infrastructure\Observability\PaymentLogger;
use App\Payments\Infrastructure\Persistence\Models\PaymentModel;
use App\Payments\Infrastructure\Providers\CloudPaymentsProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class CloudPaymentsWebhookTest extends TestCase
{
    use RefreshDatabase;

    private string $transactionId = '12345678';
    private string $publicId      = 'pk_test_public';
    private string $apiSecret     = 'test-api-secret';

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'payments.cloudpayments.public_id'  => $this->publicId,
            'payments.cloudpayments.api_secret'  => $this->apiSecret,
        ]);
    }

    private function createPayment(string $status = 'Pending'): string
    {
        $id = PaymentId::generate()->toString();

        PaymentModel::create([
            'id'              => $id,
            'external_id'     => $this->transactionId,
            'provider'        => 'cloudpayments',
            'amount'          => 50000,
            'refunded_amount' => 0,
            'currency'        => 'RUB',
            'status'          => $status,
            'description'     => 'Test CloudPayments payment',
            'idempotency_key' => (string) Str::uuid(),
            'metadata'        => [],
        ]);

        return $id;
    }

    private function validHmacHeaders(string $body): array
    {
        $hmac = base64_encode(hash_hmac('sha256', $body, $this->apiSecret, true));

        return ['Content-HMAC' => $hmac];
    }

    // ─── HTTP layer ──────────────────────────────────────────────────────────

    public function test_valid_webhook_dispatches_job(): void
    {
        Queue::fake();

        $payload = [
            'TransactionId' => (int) $this->transactionId,
            'Status'        => 'Completed',
            'Amount'        => 500.00,
        ];
        $body    = json_encode($payload);
        $headers = $this->validHmacHeaders($body);

        $response = $this->postJson('/api/webhook/cloudpayments', $payload, $headers);

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonPath('code', 0);

        Queue::assertPushed(ProcessCloudPaymentsWebhookJob::class);
    }

    public function test_invalid_hmac_returns_403(): void
    {
        Queue::fake();

        $response = $this->postJson('/api/webhook/cloudpayments', [
            'TransactionId' => (int) $this->transactionId,
            'Status'        => 'Completed',
        ], ['Content-HMAC' => 'invalid-signature']);

        $response->assertStatus(Response::HTTP_FORBIDDEN)
            ->assertJsonPath('code', 13);

        Queue::assertNotPushed(ProcessCloudPaymentsWebhookJob::class);
    }

    public function test_missing_transaction_id_returns_403(): void
    {
        Queue::fake();

        $payload = ['Status' => 'Completed'];
        $body    = json_encode($payload);
        $headers = $this->validHmacHeaders($body);

        $response = $this->postJson('/api/webhook/cloudpayments', $payload, $headers);

        $response->assertStatus(Response::HTTP_FORBIDDEN);
        Queue::assertNotPushed(ProcessCloudPaymentsWebhookJob::class);
    }

    // ─── Job processing ──────────────────────────────────────────────────────

    public function test_job_marks_payment_succeeded_on_completed(): void
    {
        $paymentId = $this->createPayment('Pending');

        $this->runJob(['TransactionId' => $this->transactionId, 'Status' => 'Completed']);

        $this->assertDatabaseHas('payments', [
            'id'     => $paymentId,
            'status' => 'Succeeded',
        ]);
    }

    public function test_job_marks_payment_succeeded_on_authorized(): void
    {
        $paymentId = $this->createPayment('Pending');

        $this->runJob(['TransactionId' => $this->transactionId, 'Status' => 'Authorized']);

        $this->assertDatabaseHas('payments', ['id' => $paymentId, 'status' => 'Succeeded']);
    }

    public function test_job_cancels_payment_on_cancelled(): void
    {
        $paymentId = $this->createPayment('Pending');

        $this->runJob(['TransactionId' => $this->transactionId, 'Status' => 'Cancelled']);

        $this->assertDatabaseHas('payments', ['id' => $paymentId, 'status' => 'Cancelled']);
    }

    public function test_job_cancels_payment_on_declined(): void
    {
        $paymentId = $this->createPayment('Pending');

        $this->runJob(['TransactionId' => $this->transactionId, 'Status' => 'Declined']);

        $this->assertDatabaseHas('payments', ['id' => $paymentId, 'status' => 'Cancelled']);
    }

    public function test_job_refunds_payment_on_refunded(): void
    {
        $paymentId = $this->createPayment('Succeeded');

        $this->runJob([
            'TransactionId' => $this->transactionId,
            'Status'        => 'Refunded',
            'Amount'        => 500.00,
        ]);

        $this->assertDatabaseHas('payments', ['id' => $paymentId, 'status' => 'Refunded']);
    }

    public function test_job_ignores_unknown_status(): void
    {
        $paymentId = $this->createPayment('Pending');

        $this->runJob(['TransactionId' => $this->transactionId, 'Status' => 'Created']);

        $this->assertDatabaseHas('payments', ['id' => $paymentId, 'status' => 'Pending']);
    }

    public function test_job_does_nothing_when_payment_not_found(): void
    {
        $this->runJob(['TransactionId' => '99999999', 'Status' => 'Completed']);

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_job_is_idempotent_on_already_succeeded(): void
    {
        $paymentId = $this->createPayment('Succeeded');

        // Duplicate Completed webhook should not crash
        $this->runJob(['TransactionId' => $this->transactionId, 'Status' => 'Completed']);

        $this->assertDatabaseHas('payments', ['id' => $paymentId, 'status' => 'Succeeded']);
    }

    // ─── Private helpers ─────────────────────────────────────────────────────

    /** @param array<string, mixed> $payload */
    private function runJob(array $payload): void
    {
        $job = new ProcessCloudPaymentsWebhookJob($payload);
        $job->handle(
            $this->app->make(PaymentRepositoryInterface::class),
            $this->app->make(PaymentLogger::class),
            $this->app->make(MetricsService::class),
        );
    }
}
