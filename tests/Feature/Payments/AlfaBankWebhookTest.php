<?php

declare(strict_types=1);

namespace Tests\Feature\Payments;

use App\Payments\Domain\Contracts\PaymentRepositoryInterface;
use App\Payments\Domain\ValueObjects\PaymentId;
use App\Payments\Infrastructure\Jobs\ProcessAlfaBankWebhookJob;
use App\Payments\Infrastructure\Observability\PaymentLogger;
use App\Payments\Infrastructure\Persistence\Models\PaymentModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class AlfaBankWebhookTest extends TestCase
{
    use RefreshDatabase;

    private string $mdOrder = 'alfa-order-uuid-0001';

    private function createPayment(string $status = 'Pending', string $externalId = ''): string
    {
        $id = PaymentId::generate()->toString();

        PaymentModel::create([
            'id'              => $id,
            'external_id'     => $externalId ?: $this->mdOrder,
            'provider'        => 'alfabank',
            'amount'          => 7500,
            'refunded_amount' => 0,
            'currency'        => 'RUB',
            'status'          => $status,
            'description'     => 'Test Alfa-Bank payment',
            'idempotency_key' => (string) Str::uuid(),
            'metadata'        => [],
        ]);

        return $id;
    }

    // ─── HTTP layer ──────────────────────────────────────────────────────────

    public function test_valid_webhook_dispatches_job(): void
    {
        Queue::fake();

        $response = $this->post('/api/webhook/alfabank', [
            'mdOrder'   => $this->mdOrder,
            'operation' => 'deposited',
            'status'    => '1',
        ]);

        $response->assertStatus(Response::HTTP_OK);
        Queue::assertPushed(ProcessAlfaBankWebhookJob::class);
    }

    public function test_missing_md_order_returns_403(): void
    {
        Queue::fake();

        $response = $this->post('/api/webhook/alfabank', [
            'operation' => 'deposited',
        ]);

        $response->assertStatus(Response::HTTP_FORBIDDEN);
        Queue::assertNotPushed(ProcessAlfaBankWebhookJob::class);
    }

    public function test_missing_operation_returns_403(): void
    {
        Queue::fake();

        $response = $this->post('/api/webhook/alfabank', [
            'mdOrder' => $this->mdOrder,
        ]);

        $response->assertStatus(Response::HTTP_FORBIDDEN);
        Queue::assertNotPushed(ProcessAlfaBankWebhookJob::class);
    }

    // ─── Job processing ──────────────────────────────────────────────────────

    public function test_job_marks_payment_succeeded_on_deposited(): void
    {
        $paymentId = $this->createPayment('Pending');

        $job = new ProcessAlfaBankWebhookJob([
            'mdOrder'   => $this->mdOrder,
            'operation' => 'deposited',
        ]);
        $job->handle(
            $this->app->make(PaymentRepositoryInterface::class),
            $this->app->make(PaymentLogger::class),
        );

        $this->assertDatabaseHas('payments', [
            'id'     => $paymentId,
            'status' => 'Succeeded',
        ]);
    }

    public function test_job_refunds_payment_on_refunded(): void
    {
        $paymentId = $this->createPayment('Succeeded');

        $job = new ProcessAlfaBankWebhookJob([
            'mdOrder'   => $this->mdOrder,
            'operation' => 'refunded',
        ]);
        $job->handle(
            $this->app->make(PaymentRepositoryInterface::class),
            $this->app->make(PaymentLogger::class),
        );

        $this->assertDatabaseHas('payments', [
            'id'     => $paymentId,
            'status' => 'Refunded',
        ]);
    }

    public function test_job_cancels_payment_on_reversed(): void
    {
        $paymentId = $this->createPayment('Pending');

        $job = new ProcessAlfaBankWebhookJob([
            'mdOrder'   => $this->mdOrder,
            'operation' => 'reversed',
        ]);
        $job->handle(
            $this->app->make(PaymentRepositoryInterface::class),
            $this->app->make(PaymentLogger::class),
        );

        $this->assertDatabaseHas('payments', [
            'id'     => $paymentId,
            'status' => 'Cancelled',
        ]);
    }

    public function test_job_cancels_payment_on_declined_by_timeout(): void
    {
        $paymentId = $this->createPayment('Pending');

        $job = new ProcessAlfaBankWebhookJob([
            'mdOrder'   => $this->mdOrder,
            'operation' => 'declinedByTimeout',
        ]);
        $job->handle(
            $this->app->make(PaymentRepositoryInterface::class),
            $this->app->make(PaymentLogger::class),
        );

        $this->assertDatabaseHas('payments', [
            'id'     => $paymentId,
            'status' => 'Cancelled',
        ]);
    }

    public function test_job_ignores_unknown_operation(): void
    {
        $paymentId = $this->createPayment('Pending');

        $job = new ProcessAlfaBankWebhookJob([
            'mdOrder'   => $this->mdOrder,
            'operation' => 'somethingElse',
        ]);
        $job->handle(
            $this->app->make(PaymentRepositoryInterface::class),
            $this->app->make(PaymentLogger::class),
        );

        $this->assertDatabaseHas('payments', [
            'id'     => $paymentId,
            'status' => 'Pending',
        ]);
    }

    public function test_job_does_nothing_when_payment_not_found(): void
    {
        $job = new ProcessAlfaBankWebhookJob([
            'mdOrder'   => 'nonexistent-order',
            'operation' => 'deposited',
        ]);
        $job->handle(
            $this->app->make(PaymentRepositoryInterface::class),
            $this->app->make(PaymentLogger::class),
        );

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_job_is_idempotent_on_already_succeeded(): void
    {
        $paymentId = $this->createPayment('Succeeded');

        // Duplicate deposited webhook should not crash
        $job = new ProcessAlfaBankWebhookJob([
            'mdOrder'   => $this->mdOrder,
            'operation' => 'deposited',
        ]);
        $job->handle(
            $this->app->make(PaymentRepositoryInterface::class),
            $this->app->make(PaymentLogger::class),
        );

        $this->assertDatabaseHas('payments', ['id' => $paymentId, 'status' => 'Succeeded']);
    }
}
