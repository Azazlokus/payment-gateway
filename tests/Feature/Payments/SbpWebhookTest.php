<?php

declare(strict_types=1);

namespace Tests\Feature\Payments;

use App\Payments\Infrastructure\Jobs\ProcessSbpWebhookJob;
use App\Payments\Infrastructure\Observability\PaymentLogger;
use App\Payments\Infrastructure\Persistence\Models\PaymentModel;
use App\Payments\Infrastructure\Providers\SbpProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class SbpWebhookTest extends TestCase
{
    use RefreshDatabase;

    private string $qrId = 'qr-sbp-test-0001';
    private string $webhookSecret = 'sbp-test-secret';

    protected function setUp(): void
    {
        parent::setUp();
        config(['payments.sbp.webhook_secret' => $this->webhookSecret]);
    }

    private function createPendingPayment(): string
    {
        $id = \App\Payments\Domain\ValueObjects\PaymentId::generate()->toString();

        PaymentModel::create([
            'id'              => $id,
            'external_id'     => $this->qrId,
            'provider'        => 'sbp',
            'amount'          => 5000,
            'refunded_amount' => 0,
            'currency'        => 'RUB',
            'status'          => 'Pending',
            'description'     => 'Test SBP payment',
            'idempotency_key' => (string) Str::uuid(),
            'metadata'        => [],
        ]);

        return $id;
    }

    private function validHeaders(): array
    {
        return ['X-Api-Key' => $this->webhookSecret];
    }

    // ─── Signature / auth ────────────────────────────────────────────────────

    public function test_valid_webhook_dispatches_job(): void
    {
        Queue::fake();

        $response = $this->postJson('/api/webhook/sbp', [
            'qrId'   => $this->qrId,
            'status' => 'PAID',
        ], $this->validHeaders());

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonPath('message', 'ok');

        Queue::assertPushed(ProcessSbpWebhookJob::class);
    }

    public function test_invalid_api_key_returns_403(): void
    {
        Queue::fake();

        $response = $this->postJson('/api/webhook/sbp', [
            'qrId'   => $this->qrId,
            'status' => 'PAID',
        ], ['X-Api-Key' => 'wrong-key']);

        $response->assertStatus(Response::HTTP_FORBIDDEN);
        Queue::assertNotPushed(ProcessSbpWebhookJob::class);
    }

    public function test_missing_api_key_returns_403(): void
    {
        Queue::fake();

        $response = $this->postJson('/api/webhook/sbp', [
            'qrId'   => $this->qrId,
            'status' => 'PAID',
        ]);

        $response->assertStatus(Response::HTTP_FORBIDDEN);
        Queue::assertNotPushed(ProcessSbpWebhookJob::class);
    }

    public function test_missing_qr_id_returns_403(): void
    {
        Queue::fake();

        $response = $this->postJson('/api/webhook/sbp', [
            'status' => 'PAID',
        ], $this->validHeaders());

        $response->assertStatus(Response::HTTP_FORBIDDEN);
        Queue::assertNotPushed(ProcessSbpWebhookJob::class);
    }

    // ─── Job processing ──────────────────────────────────────────────────────

    public function test_job_marks_payment_succeeded_on_paid(): void
    {
        $paymentId = $this->createPendingPayment();

        $job = new ProcessSbpWebhookJob(['qrId' => $this->qrId, 'status' => 'PAID']);
        $job->handle(
            $this->app->make(\App\Payments\Domain\Contracts\PaymentRepositoryInterface::class),
            $this->app->make(PaymentLogger::class),
        );

        $this->assertDatabaseHas('payments', [
            'id'     => $paymentId,
            'status' => 'Succeeded',
        ]);
    }

    public function test_job_cancels_payment_on_cancelled(): void
    {
        $paymentId = $this->createPendingPayment();

        $job = new ProcessSbpWebhookJob(['qrId' => $this->qrId, 'status' => 'CANCELLED']);
        $job->handle(
            $this->app->make(\App\Payments\Domain\Contracts\PaymentRepositoryInterface::class),
            $this->app->make(PaymentLogger::class),
        );

        $this->assertDatabaseHas('payments', [
            'id'     => $paymentId,
            'status' => 'Cancelled',
        ]);
    }

    public function test_job_cancels_payment_on_expired(): void
    {
        $paymentId = $this->createPendingPayment();

        $job = new ProcessSbpWebhookJob(['qrId' => $this->qrId, 'status' => 'EXPIRED']);
        $job->handle(
            $this->app->make(\App\Payments\Domain\Contracts\PaymentRepositoryInterface::class),
            $this->app->make(PaymentLogger::class),
        );

        $this->assertDatabaseHas('payments', [
            'id'     => $paymentId,
            'status' => 'Cancelled',
        ]);
    }

    public function test_job_ignores_unknown_status(): void
    {
        $paymentId = $this->createPendingPayment();

        $job = new ProcessSbpWebhookJob(['qrId' => $this->qrId, 'status' => 'PROCESSING']);
        $job->handle(
            $this->app->make(\App\Payments\Domain\Contracts\PaymentRepositoryInterface::class),
            $this->app->make(PaymentLogger::class),
        );

        $this->assertDatabaseHas('payments', [
            'id'     => $paymentId,
            'status' => 'Pending',
        ]);
    }

    public function test_job_does_nothing_when_payment_not_found(): void
    {
        // No payment in DB
        $job = new ProcessSbpWebhookJob(['qrId' => 'nonexistent-qr', 'status' => 'PAID']);
        $job->handle(
            $this->app->make(\App\Payments\Domain\Contracts\PaymentRepositoryInterface::class),
            $this->app->make(PaymentLogger::class),
        );

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_job_is_idempotent_on_already_succeeded_payment(): void
    {
        // Create already-succeeded payment
        $id = \App\Payments\Domain\ValueObjects\PaymentId::generate()->toString();
        PaymentModel::create([
            'id'              => $id,
            'external_id'     => $this->qrId,
            'provider'        => 'sbp',
            'amount'          => 5000,
            'refunded_amount' => 0,
            'currency'        => 'RUB',
            'status'          => 'Succeeded',
            'description'     => 'Already succeeded',
            'idempotency_key' => (string) Str::uuid(),
            'metadata'        => [],
        ]);

        // Should not throw, just silently skip
        $job = new ProcessSbpWebhookJob(['qrId' => $this->qrId, 'status' => 'PAID']);
        $job->handle(
            $this->app->make(\App\Payments\Domain\Contracts\PaymentRepositoryInterface::class),
            $this->app->make(PaymentLogger::class),
        );

        $this->assertDatabaseHas('payments', ['id' => $id, 'status' => 'Succeeded']);
    }
}
