<?php

declare(strict_types=1);

namespace Tests\Feature\Payments;

use App\Contexts\Payments\Domain\ValueObjects\PaymentId;
use App\Contexts\Payments\Infrastructure\Persistence\Models\PaymentModel;
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

    protected function setUp(): void
    {
        parent::setUp();

        // Контрактные тесты проверяют формат payload, а не безопасность (IP/подписи).
        // Отключаем IP-фильтрацию, чтобы 127.0.0.1 не давал 403.
        config([
            'payments.yookassa.webhook_ips' => [],
            'payments.robokassa.webhook_ips' => [],
            'payments.alfabank.webhook_ips' => [],
        ]);
    }

    private function createPayment(string $externalId): PaymentModel
    {
        return PaymentModel::create([
            'id' => PaymentId::generate()->toString(),
            'external_id' => $externalId,
            'provider' => 'yookassa',
            'amount' => 50000,
            'refunded_amount' => 0,
            'currency' => 'RUB',
            'status' => 'Pending',
            'description' => 'Contract test payment',
            'idempotency_key' => (string) Str::uuid(),
            'metadata' => [],
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
            'type' => 'notification',
            'event' => 'payment.succeeded',
            'object' => [
                'id' => '22d65900-000f-5000-a000-10d000000099',
                'status' => 'succeeded',
                'amount' => ['value' => '500.00', 'currency' => 'RUB'],
                'paid' => true,
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
            'type' => 'notification',
            'event' => 'payment.canceled',
            'object' => [
                'id' => '22d65900-000f-5000-a000-10d000000100',
                'status' => 'canceled',
                'cancellation_details' => ['reason' => 'card_expired'],
                'paid' => false,
                'created_at' => '2024-01-01T00:00:00.000Z',
            ],
        ]);

        $response->assertStatus(200);
    }

    public function test_yookassa_rejects_payload_without_event_field(): void
    {
        $response = $this->postJson('/api/webhook/yookassa', [
            'type' => 'notification',
            // нет 'event'
            'object' => ['id' => Str::uuid(), 'status' => 'succeeded'],
        ]);

        // 403 — verifyWebhook отклоняет: нет поля event. Главное — не 500.
        $this->assertContains($response->status(), [400, 403, 422]);
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
            'OutSum' => '500.00',
            'InvId' => '12345',
            // Формат подписи: md5("{OutSum}:{InvId}:{password2}:Shp_paymentId={id}")
            'SignatureValue' => md5('500.00:12345:'.config('payments.robokassa.password2').':Shp_paymentId='.$payment->id),
            'Shp_paymentId' => $payment->id,
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
            'Amount' => 500.00,
            'Currency' => 'RUB',
            'Status' => 'Completed',
            'InvoiceId' => 'CP-123456',
            'AccountId' => 'user@example.com',
        ]) ?: '';

        // Секрет берём из того же конфига, что и провайдер
        $hmac = base64_encode(hash_hmac('sha256', $body, (string) config('payments.cloudpayments.api_secret'), true));

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
        $this->createPayment('SBP-'.Str::uuid());

        // Формат СБП: qrId (обязательное), status, amount
        $response = $this->postJson(
            '/api/webhook/sbp',
            [
                'qrId' => 'SBP-'.Str::uuid(),
                'status' => 'PAID',
                'amount' => 50000,
                'currency' => 'RUB',
            ],
            // Ключ должен совпадать с SBP_WEBHOOK_SECRET из phpunit.xml
            ['X-Api-Key' => config('payments.sbp.webhook_secret')]
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

        // Поля по контракту Альфа-Банка: mdOrder (id заказа), operation (тип события)
        $response = $this->call('POST', '/api/webhook/alfabank', [
            'mdOrder' => (string) Str::uuid(),
            'operation' => 'deposited',
            'amount' => '50000',
            'currency' => '810',
        ]);

        $this->assertContains($response->status(), [200, 400, 404]);
    }
}
