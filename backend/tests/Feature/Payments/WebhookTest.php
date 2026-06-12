<?php

declare(strict_types=1);

namespace Tests\Feature\Payments;

use App\Contexts\Payments\Infrastructure\Jobs\ProcessYooKassaWebhookJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * Тест HTTP-слоя вебхуков YooKassa.
 *
 * WebhookController инжектит final YooKassaProvider напрямую, поэтому
 * вместо мока используем конфиг: пустой webhook_ips пропускает все IP,
 * а CIDR 192.0.2.0/24 (RFC 5737 TEST-NET) отклоняет localhost.
 */
class WebhookTest extends TestCase
{
    use RefreshDatabase;

    private string $externalId = '22d65900-000f-5000-a000-10d000000099';

    public function test_webhook_dispatches_job_on_valid_payload(): void
    {
        Queue::fake();

        // Отключаем IP-фильтрацию — пропускаем любой IP
        config(['payments.yookassa.webhook_ips' => []]);

        $response = $this->postJson('/api/webhook/yookassa', [
            'event' => 'payment.succeeded',
            'object' => [
                'id' => $this->externalId,
                'status' => 'succeeded',
            ],
        ]);

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonPath('message', 'ok');

        Queue::assertPushed(ProcessYooKassaWebhookJob::class);
    }

    public function test_webhook_returns_403_for_rejected_ip(): void
    {
        // Разрешаем только TEST-NET CIDR — localhost не пройдёт
        config(['payments.yookassa.webhook_ips' => ['192.0.2.0/24']]);

        $response = $this->postJson('/api/webhook/yookassa', [
            'event' => 'payment.succeeded',
            'object' => ['id' => $this->externalId, 'status' => 'succeeded'],
        ]);

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    public function test_webhook_does_not_dispatch_job_when_rejected(): void
    {
        Queue::fake();

        // Разрешаем только TEST-NET CIDR — localhost не пройдёт
        config(['payments.yookassa.webhook_ips' => ['192.0.2.0/24']]);

        $this->postJson('/api/webhook/yookassa', [
            'event' => 'payment.succeeded',
            'object' => ['id' => $this->externalId, 'status' => 'succeeded'],
        ]);

        Queue::assertNotPushed(ProcessYooKassaWebhookJob::class);
    }
}
