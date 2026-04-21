<?php

declare(strict_types=1);

namespace Tests\Feature\Payments;

use App\Payments\Application\PaymentProviderRegistry;
use App\Payments\Domain\Contracts\PaymentProviderInterface;
use App\Payments\Domain\Contracts\ProviderResponse;
use App\Payments\Domain\ValueObjects\ExternalId;
use App\Payments\Domain\ValueObjects\PaymentId;
use App\Payments\Infrastructure\Persistence\Models\PaymentModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class RefundPaymentTest extends TestCase
{
    use RefreshDatabase;

    private string $externalId = '22d65900-000f-5000-a000-10d000000099';

    /** Creates a payment row in Succeeded status directly, bypassing the provider. */
    private function createSucceededPayment(int $amountKopecks = 10000): string
    {
        $id = PaymentId::generate()->toString();

        PaymentModel::create([
            'id' => $id,
            'external_id' => $this->externalId,
            'provider' => 'yookassa',
            'amount' => $amountKopecks,
            'refunded_amount' => 0,
            'currency' => 'RUB',
            'status' => 'Succeeded',
            'description' => 'Test payment',
            'idempotency_key' => (string) Str::uuid(),
            'metadata' => [],
        ]);

        return $id;
    }

    private function mockRefundProvider(?ProviderResponse $response = null): void
    {
        $externalId = $this->externalId;
        $mockProvider = $this->mock(PaymentProviderInterface::class, function ($mock) use ($externalId, $response) {
            $mock->shouldReceive('name')->andReturn('yookassa');
            $mock->shouldReceive('refundPayment')->andReturn($response ?? new ProviderResponse(
                externalId: ExternalId::fromString($externalId),
                confirmationUrl: '',
                status: 'succeeded',
            ));
        });

        // Register the mock into the registry so the handler resolves it by name
        $this->app->make(PaymentProviderRegistry::class)->register($mockProvider);
    }

    // ─── Full refund ─────────────────────────────────────────────────────────

    public function test_full_refund_transitions_payment_to_refunded(): void
    {
        $id = $this->createSucceededPayment(10000);
        $this->mockRefundProvider();

        $response = $this->postJson("/api/payments/{$id}/refund");

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonPath('status', 'Refunded')
            ->assertJsonPath('amount', 10000);
    }

    public function test_full_refund_when_amount_not_specified(): void
    {
        $id = $this->createSucceededPayment(5000);
        $this->mockRefundProvider();

        // Не передаём amount — должен вернуть всю сумму
        $response = $this->postJson("/api/payments/{$id}/refund", [
            'reason' => 'Клиент передумал',
        ]);

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonPath('status', 'Refunded');
    }

    // ─── Partial refund ───────────────────────────────────────────────────────

    public function test_partial_refund_keeps_succeeded_status(): void
    {
        $id = $this->createSucceededPayment(10000);
        $this->mockRefundProvider();

        $response = $this->postJson("/api/payments/{$id}/refund", [
            'amount' => 3000,
        ]);

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonPath('status', 'Succeeded'); // частичный — остаётся Succeeded
    }

    public function test_two_partial_refunds_complete_full_refund(): void
    {
        $id = $this->createSucceededPayment(10000);
        $this->mockRefundProvider();

        $this->postJson("/api/payments/{$id}/refund", ['amount' => 4000])
            ->assertStatus(Response::HTTP_OK)
            ->assertJsonPath('status', 'Succeeded');

        $this->postJson("/api/payments/{$id}/refund", ['amount' => 6000])
            ->assertStatus(Response::HTTP_OK)
            ->assertJsonPath('status', 'Refunded');
    }

    public function test_refund_exceeding_payment_amount_returns_409(): void
    {
        $id = $this->createSucceededPayment(10000);
        $this->mockRefundProvider();

        // Сначала частичный рефанд
        $this->postJson("/api/payments/{$id}/refund", ['amount' => 8000])
            ->assertStatus(Response::HTTP_OK);

        // Теперь пытаемся вернуть больше остатка
        $response = $this->postJson("/api/payments/{$id}/refund", ['amount' => 5000]);

        $response->assertStatus(Response::HTTP_CONFLICT);
    }

    // ─── Error cases ──────────────────────────────────────────────────────────

    public function test_refund_returns_404_for_unknown_payment(): void
    {
        $fakeId = PaymentId::generate()->toString();

        $response = $this->postJson("/api/payments/{$fakeId}/refund");

        $response->assertStatus(Response::HTTP_NOT_FOUND);
    }

    public function test_refund_returns_409_for_pending_payment(): void
    {
        $id = PaymentId::generate()->toString();
        PaymentModel::create([
            'id' => $id,
            'provider' => 'yookassa',
            'amount' => 10000,
            'refunded_amount' => 0,
            'currency' => 'RUB',
            'status' => 'Pending',
            'description' => 'Test',
            'idempotency_key' => (string) Str::uuid(),
            'metadata' => [],
        ]);

        $mockProvider = $this->mock(PaymentProviderInterface::class, function ($mock) {
            $mock->shouldReceive('name')->andReturn('yookassa');
        });
        $this->app->make(PaymentProviderRegistry::class)->register($mockProvider);

        $response = $this->postJson("/api/payments/{$id}/refund");

        $response->assertStatus(Response::HTTP_CONFLICT);
    }

    public function test_refund_validates_minimum_amount(): void
    {
        $id = $this->createSucceededPayment();

        $response = $this->postJson("/api/payments/{$id}/refund", ['amount' => 50]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonValidationErrors(['amount']);
    }

    // ─── Idempotency ──────────────────────────────────────────────────────────

    public function test_refund_is_idempotent_with_same_key(): void
    {
        $id = $this->createSucceededPayment(10000);
        $key = (string) Str::uuid();

        // Провайдер должен быть вызван только один раз
        $mockProvider = $this->mock(PaymentProviderInterface::class, function ($mock) {
            $mock->shouldReceive('name')->andReturn('yookassa');
            $mock->shouldReceive('refundPayment')->once()->andReturn(new ProviderResponse(
                externalId: ExternalId::fromString($this->externalId),
                confirmationUrl: '',
                status: 'succeeded',
            ));
        });
        $this->app->make(PaymentProviderRegistry::class)->register($mockProvider);

        $first = $this->postJson("/api/payments/{$id}/refund", [], ['Idempotency-Key' => $key]);
        $first->assertStatus(Response::HTTP_OK)->assertJsonPath('status', 'Refunded');

        // Второй запрос — идёт из кэша, провайдер не вызывается
        $second = $this->postJson("/api/payments/{$id}/refund", [], ['Idempotency-Key' => $key]);
        $second->assertStatus(Response::HTTP_OK)
            ->assertJsonPath('status', 'Refunded')
            ->assertJsonPath('id', $first->json('id'));
    }

    public function test_different_idempotency_keys_create_separate_refunds(): void
    {
        $id = $this->createSucceededPayment(10000);

        $mockProvider = $this->mock(PaymentProviderInterface::class, function ($mock) {
            $mock->shouldReceive('name')->andReturn('yookassa');
            $mock->shouldReceive('refundPayment')->twice()->andReturn(new ProviderResponse(
                externalId: ExternalId::fromString($this->externalId),
                confirmationUrl: '',
                status: 'succeeded',
            ));
        });
        $this->app->make(PaymentProviderRegistry::class)->register($mockProvider);

        $this->postJson("/api/payments/{$id}/refund", ['amount' => 4000], ['Idempotency-Key' => (string) Str::uuid()])
            ->assertStatus(Response::HTTP_OK)
            ->assertJsonPath('status', 'Succeeded');

        $this->postJson("/api/payments/{$id}/refund", ['amount' => 6000], ['Idempotency-Key' => (string) Str::uuid()])
            ->assertStatus(Response::HTTP_OK)
            ->assertJsonPath('status', 'Refunded');
    }

    public function test_idempotency_cache_is_not_polluted_on_failed_refund(): void
    {
        $fakeId = PaymentId::generate()->toString();
        $key = (string) Str::uuid();

        $this->postJson("/api/payments/{$fakeId}/refund", [], ['Idempotency-Key' => $key])
            ->assertStatus(Response::HTTP_NOT_FOUND);

        // Ключ не должен попасть в кэш при ошибке
        $this->assertNull(Cache::get("refund_idem:{$key}"));
    }
}
