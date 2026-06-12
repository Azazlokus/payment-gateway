<?php

declare(strict_types=1);

namespace Tests\Feature\Payments;

use App\Contexts\Payments\Application\PaymentProviderRegistry;
use App\Contexts\Payments\Domain\Contracts\PaymentProviderInterface;
use App\Contexts\Payments\Domain\Contracts\ProviderResponse;
use App\Contexts\Payments\Domain\Contracts\SupportsTokenization;
use App\Contexts\Payments\Domain\Contracts\TokenizationResult;
use App\Contexts\Payments\Domain\ValueObjects\ExternalId;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Mockery;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class TokenizePaymentMethodTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Регистрирует мок-провайдер 'yookassa', поддерживающий и обычные платежи,
     * и токенизацию. tokenize() всегда возвращает одну и ту же карту, поэтому
     * fingerprint стабилен между вызовами.
     */
    private function mockTokenizingProvider(string $token = 'tok_live_1'): void
    {
        $provider = Mockery::mock(PaymentProviderInterface::class, SupportsTokenization::class);
        $provider->shouldReceive('name')->andReturn('yookassa');
        $provider->shouldReceive('createPayment')->andReturn(new ProviderResponse(
            externalId: ExternalId::fromString('22d65900-000f-5000-a000-10d000000001'),
            confirmationUrl: 'https://yookassa.ru/checkout/test',
            status: 'pending',
        ));
        $provider->shouldReceive('tokenize')->andReturn(new TokenizationResult(
            token: $token,
            type: 'card',
            last4: '4242',
            brand: 'Visa',
            expiresAt: '12/2030',
        ));
        $provider->shouldReceive('deleteToken');

        $this->app->make(PaymentProviderRegistry::class)->register($provider);
    }

    private function createPaymentId(): string
    {
        return $this->postJson('/api/v1/payments', [
            'amount' => 10000,
            'description' => 'Test order',
            'return_url' => 'https://example.com/success',
        ])->json('id');
    }

    public function test_tokenize_creates_payment_method_and_hides_token(): void
    {
        $this->mockTokenizingProvider();
        $paymentId = $this->createPaymentId();

        $response = $this->postJson('/api/v1/payment-methods', [
            'payment_id' => $paymentId,
            'customer_id' => 'cust_777',
        ]);

        $response->assertStatus(Response::HTTP_CREATED)
            ->assertJsonPath('customer_id', 'cust_777')
            ->assertJsonPath('provider', 'yookassa')
            ->assertJsonPath('type', 'card')
            ->assertJsonPath('last4', '4242')
            ->assertJsonPath('brand', 'Visa')
            ->assertJsonPath('is_active', true);

        // Токен никогда не отдаётся наружу
        $response->assertJsonMissingPath('token');

        $this->assertDatabaseHas('payment_methods', [
            'customer_id' => 'cust_777',
            'last4' => '4242',
            'is_active' => true,
        ]);
    }

    public function test_tokenize_is_idempotent_for_same_card(): void
    {
        $this->mockTokenizingProvider();
        $customerId = 'cust_dedup';

        $first = $this->postJson('/api/v1/payment-methods', [
            'payment_id' => $this->createPaymentId(),
            'customer_id' => $customerId,
        ])->assertStatus(Response::HTTP_CREATED);

        $second = $this->postJson('/api/v1/payment-methods', [
            'payment_id' => $this->createPaymentId(),
            'customer_id' => $customerId,
        ])->assertStatus(Response::HTTP_CREATED);

        // Та же карта → та же запись, без дубля
        $this->assertSame($first->json('id'), $second->json('id'));
        $this->assertDatabaseCount('payment_methods', 1);
    }

    public function test_list_returns_only_active_methods(): void
    {
        $this->mockTokenizingProvider();

        $created = $this->postJson('/api/v1/payment-methods', [
            'payment_id' => $this->createPaymentId(),
            'customer_id' => 'cust_list',
        ]);

        $this->getJson('/api/v1/payment-methods?customer_id=cust_list')
            ->assertStatus(Response::HTTP_OK)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $created->json('id'));
    }

    public function test_delete_deactivates_method(): void
    {
        $this->mockTokenizingProvider();

        $id = $this->postJson('/api/v1/payment-methods', [
            'payment_id' => $this->createPaymentId(),
            'customer_id' => 'cust_del',
        ])->json('id');

        $this->deleteJson("/api/v1/payment-methods/{$id}")
            ->assertStatus(Response::HTTP_NO_CONTENT);

        // Строка остаётся (мягкое удаление), но из списка пропадает
        $this->assertDatabaseHas('payment_methods', ['id' => $id, 'is_active' => false]);

        $this->getJson('/api/v1/payment-methods?customer_id=cust_del')
            ->assertJsonCount(0, 'data');
    }

    public function test_retokenizing_deleted_card_reactivates_without_duplicate(): void
    {
        $this->mockTokenizingProvider();
        $customerId = 'cust_readd';

        $id = $this->postJson('/api/v1/payment-methods', [
            'payment_id' => $this->createPaymentId(),
            'customer_id' => $customerId,
        ])->json('id');

        $this->deleteJson("/api/v1/payment-methods/{$id}")
            ->assertStatus(Response::HTTP_NO_CONTENT);

        // Повторно добавляем ту же карту (тот же fingerprint)
        $reAdded = $this->postJson('/api/v1/payment-methods', [
            'payment_id' => $this->createPaymentId(),
            'customer_id' => $customerId,
        ])->assertStatus(Response::HTTP_CREATED);

        // Переиспользована та же запись — никакого нарушения unique-индекса и дубля
        $this->assertSame($id, $reAdded->json('id'));
        $reAdded->assertJsonPath('is_active', true);
        $this->assertDatabaseCount('payment_methods', 1);

        $this->getJson("/api/v1/payment-methods?customer_id={$customerId}")
            ->assertJsonCount(1, 'data');
    }

    public function test_tokenize_rejects_unknown_payment(): void
    {
        $this->mockTokenizingProvider();

        $this->postJson('/api/v1/payment-methods', [
            'payment_id' => (string) Str::ulid(),
            'customer_id' => 'cust_x',
        ])->assertStatus(Response::HTTP_NOT_FOUND);
    }
}
