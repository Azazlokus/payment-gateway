<?php

declare(strict_types=1);

namespace Tests\Feature\Payments;

use App\Payments\Domain\Contracts\PaymentProviderInterface;
use App\Payments\Domain\Contracts\ProviderResponse;
use App\Payments\Domain\ValueObjects\ExternalId;
use App\Payments\Infrastructure\Jobs\ProcessYooKassaWebhookJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class WebhookTest extends TestCase
{
    use RefreshDatabase;

    private string $externalId = '22d65900-000f-5000-a000-10d000000099';

    private function mockProviderAccept(): void
    {
        $this->mock(PaymentProviderInterface::class, function ($mock) {
            $mock->shouldReceive('name')->andReturn('yookassa');
            $mock->shouldReceive('verifyWebhook')->andReturn(true);
            $mock->shouldReceive('createPayment')->andReturn(new ProviderResponse(
                externalId: ExternalId::fromString($this->externalId),
                confirmationUrl: 'https://yookassa.ru/checkout/test',
                status: 'pending',
            ));
            $mock->shouldReceive('parseWebhook')->andReturn(new ProviderResponse(
                externalId: ExternalId::fromString($this->externalId),
                confirmationUrl: '',
                status: 'succeeded',
            ));
        });
    }

    private function mockProviderReject(): void
    {
        $this->mock(PaymentProviderInterface::class, function ($mock) {
            $mock->shouldReceive('name')->andReturn('yookassa');
            $mock->shouldReceive('verifyWebhook')->andReturn(false);
        });
    }

    public function test_webhook_dispatches_job_on_valid_payload(): void
    {
        Queue::fake();
        $this->mockProviderAccept();

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
        $this->mockProviderReject();

        $response = $this->postJson('/api/webhook/yookassa', [
            'event' => 'payment.succeeded',
            'object' => ['id' => $this->externalId, 'status' => 'succeeded'],
        ]);

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    public function test_webhook_does_not_dispatch_job_when_rejected(): void
    {
        Queue::fake();
        $this->mockProviderReject();

        $this->postJson('/api/webhook/yookassa', [
            'event' => 'payment.succeeded',
            'object' => ['id' => $this->externalId, 'status' => 'succeeded'],
        ]);

        Queue::assertNotPushed(ProcessYooKassaWebhookJob::class);
    }
}
