<?php

declare(strict_types=1);

namespace Tests\Feature\Payments;

use App\Contexts\Payments\Application\PaymentProviderRegistry;
use App\Contexts\Payments\Domain\Contracts\PaymentProviderInterface;
use App\Contexts\Payments\Domain\Contracts\ProviderResponse;
use App\Contexts\Payments\Domain\ValueObjects\ExternalId;
use App\Contexts\Payments\Domain\ValueObjects\PaymentId;
use App\Contexts\Payments\Infrastructure\Persistence\Models\PaymentModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class RetryPaymentTest extends TestCase
{
    use RefreshDatabase;

    private function mockProvider(string $provider = 'yookassa'): void
    {
        $mock = $this->mock(PaymentProviderInterface::class, function ($mock) use ($provider): void {
            $mock->shouldReceive('name')->andReturn($provider);
            $mock->shouldReceive('createPayment')->andReturn(new ProviderResponse(
                externalId: ExternalId::fromString('new-ext-id-'.uniqid()),
                confirmationUrl: 'https://pay.example.com/new',
                status: 'pending',
            ));
        });
        $this->app->make(PaymentProviderRegistry::class)->register($mock);
    }

    private function createPayment(string $status, string $provider = 'yookassa'): string
    {
        $id = PaymentId::generate()->toString();

        PaymentModel::create([
            'id' => $id,
            'external_id' => 'ext-'.$id,
            'provider' => $provider,
            'amount' => 50000,
            'refunded_amount' => 0,
            'currency' => 'RUB',
            'status' => $status,
            'description' => 'Test payment',
            'idempotency_key' => (string) Str::uuid(),
            'metadata' => [],
        ]);

        return $id;
    }

    public function test_retry_creates_new_payment_for_cancelled(): void
    {
        $this->mockProvider();
        $id = $this->createPayment('Cancelled');

        $response = $this->postJson("/api/v1/payments/{$id}/retry", [
            'return_url' => 'https://example.com/return',
        ]);

        $response->assertStatus(Response::HTTP_CREATED);
        $response->assertJsonPath('status', 'Pending');

        // Оригинальный платёж не изменился
        $this->assertDatabaseHas('payments', ['id' => $id, 'status' => 'Cancelled']);
        // Новый платёж создан
        $this->assertDatabaseCount('payments', 2);
    }

    public function test_retry_returns_409_for_pending_payment(): void
    {
        $id = $this->createPayment('Pending');

        $response = $this->postJson("/api/v1/payments/{$id}/retry", [
            'return_url' => 'https://example.com/return',
        ]);

        $response->assertStatus(Response::HTTP_CONFLICT);
        $this->assertDatabaseCount('payments', 1);
    }

    public function test_retry_returns_409_for_succeeded_payment(): void
    {
        $id = $this->createPayment('Succeeded');

        $response = $this->postJson("/api/v1/payments/{$id}/retry", [
            'return_url' => 'https://example.com/return',
        ]);

        $response->assertStatus(Response::HTTP_CONFLICT);
    }

    public function test_retry_returns_404_for_unknown_payment(): void
    {
        $fakeId = PaymentId::generate()->toString();

        $response = $this->postJson("/api/v1/payments/{$fakeId}/retry", [
            'return_url' => 'https://example.com/return',
        ]);

        $response->assertStatus(Response::HTTP_NOT_FOUND);
    }

    public function test_retry_new_payment_has_retried_from_metadata(): void
    {
        $this->mockProvider();
        $originalId = $this->createPayment('Cancelled');

        $this->postJson("/api/v1/payments/{$originalId}/retry", [
            'return_url' => 'https://example.com/return',
        ]);

        $newPayment = PaymentModel::where('id', '!=', $originalId)->first();
        $this->assertNotNull($newPayment);
        $this->assertSame($originalId, $newPayment->metadata['retried_from']);
    }
}
