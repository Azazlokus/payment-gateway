<?php

declare(strict_types=1);

namespace Tests\Feature\Payments;

use App\Payments\Application\PaymentProviderRegistry;
use App\Payments\Domain\Contracts\PaymentProviderInterface;
use App\Payments\Domain\Contracts\ProviderResponse;
use App\Payments\Domain\ValueObjects\ExternalId;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class CreatePaymentTest extends TestCase
{
    use RefreshDatabase;

    private function mockProvider(
        string $externalId = '22d65900-000f-5000-a000-10d000000001',
        string $confirmationUrl = 'https://yookassa.ru/checkout/test',
        string $status = 'pending',
    ): void {
        $mockProvider = $this->mock(PaymentProviderInterface::class, function ($mock) use ($externalId, $confirmationUrl, $status) {
            $mock->shouldReceive('name')->andReturn('yookassa');
            $mock->shouldReceive('createPayment')->andReturn(new ProviderResponse(
                externalId: ExternalId::fromString($externalId),
                confirmationUrl: $confirmationUrl,
                status: $status,
            ));
        });
        $this->app->make(PaymentProviderRegistry::class)->register($mockProvider);
    }

    public function test_creates_payment_successfully(): void
    {
        $this->mockProvider();

        $response = $this->postJson('/api/v1/payments', [
            'amount' => 10000,
            'description' => 'Test order',
            'return_url' => 'https://example.com/success',
        ]);

        $response->assertStatus(Response::HTTP_CREATED)
            ->assertJsonStructure(['id', 'status', 'amount', 'currency', 'confirmation_url', 'external_id'])
            ->assertJsonPath('status', 'Pending')
            ->assertJsonPath('amount', 10000)
            ->assertJsonPath('currency', 'RUB')
            ->assertJsonPath('confirmation_url', 'https://yookassa.ru/checkout/test');
    }

    public function test_create_is_idempotent_with_same_key(): void
    {
        $this->mockProvider();

        $key = (string) Str::uuid();

        $first = $this->postJson('/api/v1/payments', [
            'amount' => 10000,
            'description' => 'Test order',
            'return_url' => 'https://example.com/success',
        ], ['Idempotency-Key' => $key]);

        $first->assertStatus(Response::HTTP_CREATED);
        $firstId = $first->json('id');

        // Second request with same key — should return same payment without calling provider again
        $mockProvider2 = $this->mock(PaymentProviderInterface::class, function ($mock) {
            $mock->shouldReceive('name')->andReturn('yookassa');
            $mock->shouldNotReceive('createPayment');
        });
        $this->app->make(PaymentProviderRegistry::class)->register($mockProvider2);

        $second = $this->postJson('/api/v1/payments', [
            'amount' => 10000,
            'description' => 'Test order',
            'return_url' => 'https://example.com/success',
        ], ['Idempotency-Key' => $key]);

        $second->assertStatus(Response::HTTP_CREATED)
            ->assertJsonPath('id', $firstId);
    }

    public function test_validation_rejects_missing_fields(): void
    {
        $response = $this->postJson('/api/v1/payments', []);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonValidationErrors(['amount', 'description', 'return_url']);
    }

    public function test_validation_rejects_non_https_return_url(): void
    {
        $response = $this->postJson('/api/v1/payments', [
            'amount' => 10000,
            'description' => 'Test',
            'return_url' => 'http://example.com/success',
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonValidationErrors(['return_url']);
    }

    public function test_validation_rejects_amount_below_minimum(): void
    {
        $response = $this->postJson('/api/v1/payments', [
            'amount' => 50,
            'description' => 'Test',
            'return_url' => 'https://example.com/success',
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonValidationErrors(['amount']);
    }

    public function test_get_payment_returns_404_for_unknown_id(): void
    {
        $response = $this->getJson('/api/v1/payments/01HHHHHHHHHHHHHHHHHHHHHHH');

        $response->assertStatus(Response::HTTP_NOT_FOUND)
            ->assertJsonPath('error', 'not_found');
    }

    public function test_get_payment_returns_404_for_invalid_ulid(): void
    {
        $response = $this->getJson('/api/v1/payments/not-a-valid-ulid');

        $response->assertStatus(Response::HTTP_NOT_FOUND);
    }

    public function test_lists_payments_with_pagination(): void
    {
        $this->mockProvider();

        // Create a payment first
        $this->postJson('/api/v1/payments', [
            'amount' => 10000,
            'description' => 'Test order',
            'return_url' => 'https://example.com/success',
        ]);

        $response = $this->getJson('/api/v1/payments');

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure(['data', 'total', 'per_page', 'current_page', 'last_page'])
            ->assertJsonPath('total', 1)
            ->assertJsonPath('current_page', 1);
    }

    public function test_lists_payments_filtered_by_provider(): void
    {
        $this->mockProvider();

        // Create a payment through the API (provider = yookassa from mock)
        $this->postJson('/api/v1/payments', [
            'amount'      => 10000,
            'description' => 'Test order',
            'return_url'  => 'https://example.com/success',
        ]);

        // Filter by matching provider
        $this->getJson('/api/v1/payments?provider=yookassa')
            ->assertStatus(200)
            ->assertJsonPath('total', 1);

        // Filter by non-matching provider
        $this->getJson('/api/v1/payments?provider=robokassa')
            ->assertStatus(200)
            ->assertJsonPath('total', 0);
    }

    public function test_health_check_returns_ok(): void
    {
        $response = $this->getJson('/api/health');

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('db', 'ok');
    }
}
