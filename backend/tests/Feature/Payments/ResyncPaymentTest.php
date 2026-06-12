<?php

declare(strict_types=1);

namespace Tests\Feature\Payments;

use App\Contexts\Payments\Domain\ValueObjects\PaymentId;
use App\Contexts\Payments\Infrastructure\Observability\NotificationService;
use App\Contexts\Payments\Infrastructure\Persistence\Models\PaymentModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Mockery;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class ResyncPaymentTest extends TestCase
{
    use RefreshDatabase;

    private function createPayment(array $metadata = []): string
    {
        $id = PaymentId::generate()->toString();

        PaymentModel::create([
            'id' => $id,
            'external_id' => 'ext-'.$id,
            'provider' => 'yookassa',
            'amount' => 50000,
            'refunded_amount' => 0,
            'currency' => 'RUB',
            'status' => 'Succeeded',
            'description' => 'Test payment',
            'idempotency_key' => (string) Str::uuid(),
            'metadata' => $metadata,
        ]);

        return $id;
    }

    public function test_resync_returns_200(): void
    {
        $id = $this->createPayment();

        $response = $this->postJson("/api/v1/payments/{$id}/resync");

        $response->assertStatus(Response::HTTP_OK);
    }

    public function test_resync_returns_404_for_unknown_id(): void
    {
        $fakeId = PaymentId::generate()->toString();

        $response = $this->postJson("/api/v1/payments/{$fakeId}/resync");

        $response->assertStatus(Response::HTTP_NOT_FOUND);
    }

    public function test_resync_calls_notification_service(): void
    {
        $notified = false;

        // Override the no-op mock set by TestCase::setUp with a spy
        $mock = Mockery::mock(NotificationService::class);
        $mock->shouldReceive('notify')
            ->once()
            ->andReturnUsing(function () use (&$notified): void {
                $notified = true;
            });
        // Re-bind after TestCase setUp already bound a different mock
        $this->app->instance(NotificationService::class, $mock);

        $id = $this->createPayment(['notification_url' => 'https://example.com/notify']);

        $this->postJson("/api/v1/payments/{$id}/resync");

        $this->assertTrue($notified);
    }

    public function test_resync_sends_notification_to_url(): void
    {
        Http::fake(['https://example.com/notify' => Http::response([], 200)]);

        $id = $this->createPayment(['notification_url' => 'https://example.com/notify']);

        $this->postJson("/api/v1/payments/{$id}/resync");

        Http::assertSent(fn ($req): bool => $req->url() === 'https://example.com/notify');
    }

    public function test_resync_without_notification_url_returns_200(): void
    {
        Http::fake();

        $id = $this->createPayment(); // нет notification_url в metadata

        $response = $this->postJson("/api/v1/payments/{$id}/resync");

        $response->assertStatus(Response::HTTP_OK);
        Http::assertNothingSent();
    }
}
