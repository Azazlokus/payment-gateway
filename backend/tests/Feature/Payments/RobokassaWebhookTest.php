<?php

declare(strict_types=1);

namespace Tests\Feature\Payments;

use App\Contexts\Payments\Domain\Contracts\PaymentRepositoryInterface;
use App\Contexts\Payments\Domain\ValueObjects\PaymentId;
use App\Contexts\Payments\Infrastructure\Jobs\ProcessRobokassaWebhookJob;
use App\Contexts\Payments\Infrastructure\Observability\PaymentLogger;
use App\Contexts\Payments\Infrastructure\Persistence\Models\PaymentModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class RobokassaWebhookTest extends TestCase
{
    use RefreshDatabase;

    private string $login = 'test_merchant';

    private string $password2 = 'test_password2';

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'payments.robokassa.login' => $this->login,
            'payments.robokassa.password1' => 'test_password1',
            'payments.robokassa.password2' => $this->password2,
            'payments.robokassa.is_test' => true,
            'payments.robokassa.webhook_ips' => [], // пропускаем IP-фильтрацию
        ]);
    }

    private function createPayment(string $status = 'Pending'): string
    {
        $id = PaymentId::generate()->toString();

        PaymentModel::create([
            'id' => $id,
            'external_id' => $id, // placeholder до первого вебхука
            'provider' => 'robokassa',
            'amount' => 30000,
            'refunded_amount' => 0,
            'currency' => 'RUB',
            'status' => $status,
            'description' => 'Test Robokassa payment',
            'idempotency_key' => (string) Str::uuid(),
            'metadata' => [],
        ]);

        return $id;
    }

    private function validPayload(string $paymentId, string $invId = '42', string $outSum = '300.00'): array
    {
        $signature = strtoupper(md5("{$outSum}:{$invId}:{$this->password2}:Shp_paymentId={$paymentId}"));

        return [
            'OutSum' => $outSum,
            'InvId' => $invId,
            'Shp_paymentId' => $paymentId,
            'SignatureValue' => $signature,
        ];
    }

    // ─── HTTP layer ──────────────────────────────────────────────────────────

    public function test_valid_webhook_dispatches_job(): void
    {
        Queue::fake();

        $paymentId = $this->createPayment();
        $payload = $this->validPayload($paymentId);

        $response = $this->post('/api/webhook/robokassa', $payload);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertSee("OK{$payload['InvId']}");

        Queue::assertPushed(ProcessRobokassaWebhookJob::class);
    }

    public function test_invalid_signature_returns_403(): void
    {
        Queue::fake();

        $paymentId = $this->createPayment();

        $response = $this->post('/api/webhook/robokassa', [
            'OutSum' => '300.00',
            'InvId' => '42',
            'Shp_paymentId' => $paymentId,
            'SignatureValue' => 'INVALIDSIGNATURE',
        ]);

        $response->assertStatus(Response::HTTP_FORBIDDEN);
        $response->assertSee('Invalid signature');

        Queue::assertNotPushed(ProcessRobokassaWebhookJob::class);
    }

    public function test_missing_shp_payment_id_returns_403(): void
    {
        Queue::fake();

        // Без Shp_paymentId подпись не совпадёт и verifyWebhook вернёт false
        $response = $this->post('/api/webhook/robokassa', [
            'OutSum' => '300.00',
            'InvId' => '42',
            'SignatureValue' => 'ANYSIGNATURE',
        ]);

        $response->assertStatus(Response::HTTP_FORBIDDEN);
        Queue::assertNotPushed(ProcessRobokassaWebhookJob::class);
    }

    public function test_response_contains_ok_with_inv_id(): void
    {
        Queue::fake();

        $paymentId = $this->createPayment();
        $payload = $this->validPayload($paymentId, '99');

        $response = $this->post('/api/webhook/robokassa', $payload);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertSee('OK99');
    }

    public function test_ip_filtering_blocks_request_when_ip_not_in_allowed_list(): void
    {
        Queue::fake();

        config(['payments.robokassa.webhook_ips' => ['185.26.103.0/24']]);

        $paymentId = $this->createPayment();
        $payload = $this->validPayload($paymentId);

        // По умолчанию тестовые запросы приходят с 127.0.0.1
        $response = $this->post('/api/webhook/robokassa', $payload);

        $response->assertStatus(Response::HTTP_FORBIDDEN);
        Queue::assertNotPushed(ProcessRobokassaWebhookJob::class);
    }

    // ─── Job processing ──────────────────────────────────────────────────────

    public function test_job_marks_payment_succeeded_and_updates_external_id(): void
    {
        $paymentId = $this->createPayment('Pending');
        $invId = '777';

        $this->runJob([
            'Shp_paymentId' => $paymentId,
            'InvId' => $invId,
            'OutSum' => '300.00',
        ]);

        $this->assertDatabaseHas('payments', [
            'id' => $paymentId,
            'status' => 'Succeeded',
            'external_id' => $invId,
        ]);
    }

    public function test_job_does_nothing_when_payment_not_found(): void
    {
        $this->runJob([
            'Shp_paymentId' => 'nonexistent-payment-id',
            'InvId' => '42',
            'OutSum' => '300.00',
        ]);

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_job_is_idempotent_on_already_succeeded(): void
    {
        $paymentId = $this->createPayment('Succeeded');

        // Повторный вебхук не должен падать
        $this->runJob([
            'Shp_paymentId' => $paymentId,
            'InvId' => '42',
            'OutSum' => '300.00',
        ]);

        $this->assertDatabaseHas('payments', ['id' => $paymentId, 'status' => 'Succeeded']);
    }

    public function test_job_does_not_process_when_shp_payment_id_is_empty(): void
    {
        $this->createPayment();

        $this->runJob([
            'Shp_paymentId' => '',
            'InvId' => '42',
            'OutSum' => '300.00',
        ]);

        // Пустой ID → payment not found → ничего не изменилось
        $this->assertDatabaseMissing('payments', ['status' => 'Succeeded']);
    }

    // ─── Private helpers ─────────────────────────────────────────────────────

    /** @param array<string, mixed> $payload */
    private function runJob(array $payload): void
    {
        $job = new ProcessRobokassaWebhookJob($payload);
        $job->handle(
            $this->app->make(PaymentRepositoryInterface::class),
            $this->app->make(PaymentLogger::class),
        );
    }
}
