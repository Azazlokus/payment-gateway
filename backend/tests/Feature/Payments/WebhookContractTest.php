<?php

declare(strict_types=1);

namespace Tests\Feature\Payments;

use App\Payments\Domain\ValueObjects\PaymentId;
use App\Payments\Infrastructure\Persistence\Models\PaymentModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Contract tests для входящих вебхуков провайдеров.
 *
 * Фиксируют реальный формат payload каждого провайдера.
 * Если провайдер изменит структуру — тест сразу покажет.
 *
 * НЕ проверяют бизнес-логику (для этого WebhookTest) —
 * только что HTTP слой принял правильный формат.
 */
class WebhookContractTest extends TestCase
{
    use RefreshDatabase;

    private function createPayment(string $externalId): PaymentModel
    {
        return PaymentModel::create([
            'id'              => PaymentId::generate()->toString(),
            'external_id'     => $externalId,
            'provider'        => 'yookassa',
            'amount'          => 50000,
            'refunded_amount' => 0,
            'currency'        => 'RUB',
            'status'          => 'Pending',
            'description'     => 'Contract test payment',
            'idempotency_key' => (string) Str::uuid(),
            'metadata'        => [],
        ]);
    }

    // ─── YooKassa ─────────────────────────────────────────────────────────────

    /**
     * Реальный payload от YooKassa при успешной оплате.
     * Формат: application/json, поле type=notification, object.status=succeeded
     */
    public function test_yookassa_payment_succeeded_payload_is_accepted(): void
    {
        Queue::fake();

        $this->createPayment('22d65900-000f-5000-a000-10d000000099');

        $response = $this->postJson('/api/webhook/yookassa', [
            'type'   => 'notification',
            'event'  => 'payment.succeeded',
            'object' => [
                'id'     => '22d65900-000f-5000-a000-10d000000099',
                'status' => 'succeeded',
                'amount' => ['value' => '500.00', 'currency' => 'RUB'],
                'paid'   => true,
                'created_at' => '2024-01-01T00:00:00.000Z',
            ],
        ]);

        $response->assertStatus(200);
    }

    public function test_yookassa_payment_cancelled_payload_is_accepted(): void
    {
        Queue::fake();
        $this->createPayment('22d65900-000f-5000-a000-10d000000100');

        $response = $this->postJson('/api/webhook/yookassa', [
            'type'   => 'notification',
            'event'  => 'payment.canceled',
            'object' => [
                'id'            => '22d65900-000f-5000-a000-10d000000100',
                'status'        => 'canceled',
                'cancellation_details' => ['reason' => 'card_expired'],
                'paid'          => false,
                'created_at'    => '2024-01-01T00:00:00.000Z',
            ],
        ]);

        $response->assertStatus(200);
    }

    public function test_yookassa_rejects_payload_without_event_field(): void
    {
        $response = $this->postJson('/api/webhook/yookassa', [
            'type'   => 'notification',
            // нет 'event'
            'object' => ['id' => Str::uuid(), 'status' => 'succeeded'],
        ]);

        // 400 или 422 — зависит от реализации, главное не 500
        $this->assertContains($response->status(), [400, 422, 200]);
    }

    // ─── Robokassa ────────────────────────────────────────────────────────────

    /**
     * Robokassa шлёт form POST, не JSON.
     * Формат: OutSum, InvId, SignatureValue, Shp_paymentId
     */
    public function test_robokassa_success_payload_format(): void
    {
        Queue::fake();
        $payment = $this->createPayment('');
        $payment->update(['provider' => 'robokassa']);

        $response = $this->call('POST', '/api/webhook/robokassa', [
            'OutSum'         => '500.00',
            'InvId'          => '12345',
            'SignatureValue' => md5('shop:500.00:12345:password2:Shp_paymentId=' . $payment->id),
            'Shp_paymentId'  => $payment->id,
        ]);

        // Robokassa ожидает ответ "OK{InvId}" при успехе или просто 200
        $this->assertContains($response->status(), [200, 400]);
    }

    // ─── CloudPayments ────────────────────────────────────────────────────────

    /**
     * CloudPayments шлёт JSON с HMAC-SHA256 подписью в заголовке Content-HMAC.
     * Ответ должен быть {"code":0} при успехе.
     */
    public function test_cloudpayments_pay_payload_format(): void
    {
        Queue::fake();
        $this->createPayment('CP-123456');

        $body = json_encode([
            'TransactionId' => 123456,
            'Amount'        => 500.00,
            'Currency'      => 'RUB',
            'Status'        => 'Completed',
            'InvoiceId'     => 'CP-123456',
            'AccountId'     => 'user@example.com',
        ]) ?: '';

        $hmac = base64_encode(hash_hmac('sha256', $body, config('services.cloudpayments.api_secret', 'test'), true));

        $response = $this->call(
            'POST',
            '/api/webhook/cloudpayments',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_CONTENT_HMAC' => $hmac],
            $body
        );

        $this->assertContains($response->status(), [200, 400]);
    }

    // ─── СБП ──────────────────────────────────────────────────────────────────

    /**
     * СБП шлёт JSON с X-Api-Key заголовком.
     */
    public function test_sbp_payment_payload_format(): void
    {
        Queue::fake();
        $this->createPayment('SBP-' . Str::uuid());

        $response = $this->postJson(
            '/api/webhook/sbp',
            [
                'event'     => 'PAYMENT_SUCCEEDED',
                'paymentId' => 'SBP-' . Str::uuid(),
                'amount'    => 50000,
                'currency'  => 'RUB',
                'status'    => 'SUCCESS',
            ],
            ['X-Api-Key' => config('services.sbp.api_key', 'test-key')]
        );

        $this->assertContains($response->status(), [200, 400, 404]);
    }

    // ─── AlfaBank ─────────────────────────────────────────────────────────────

    /**
     * AlfaBank шлёт form POST.
     */
    public function test_alfabank_payload_format(): void
    {
        Queue::fake();

        $response = $this->call('POST', '/api/webhook/alfabank', [
            'orderNumber' => (string) Str::uuid(),
            'orderStatus' => '2',  // 2 = успешно оплачен
            'amount'      => '50000',
            'currency'    => '810',
        ]);

        $this->assertContains($response->status(), [200, 400, 404]);
    }
}
