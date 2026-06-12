<?php

declare(strict_types=1);

namespace Tests\Feature\Payments;

use App\Contexts\Payments\Application\PaymentProviderRegistry;
use App\Contexts\Payments\Domain\Contracts\PaymentProviderInterface;
use App\Contexts\Payments\Domain\Contracts\ProviderResponse;
use App\Contexts\Payments\Domain\ValueObjects\ExternalId;
use App\Contexts\Payments\Domain\ValueObjects\PaymentId;
use App\Contexts\Payments\Infrastructure\Jobs\ProcessRefundJob;
use App\Contexts\Payments\Infrastructure\Persistence\Models\PaymentModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
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
        $mockProvider = $this->mock(PaymentProviderInterface::class, function ($mock) use ($externalId, $response): void {
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

    /**
     * Рефанд теперь асинхронный (saga): handler ставит ProcessRefundJob в очередь,
     * статус платежа меняется только после подтверждения провайдером.
     * На момент ответа API статус остаётся Succeeded.
     */
    public function test_full_refund_dispatches_refund_job(): void
    {
        Queue::fake();
        $id = $this->createSucceededPayment(10000);
        $this->mockRefundProvider();

        $response = $this->postJson("/api/v1/payments/{$id}/refund");

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonPath('status', 'Succeeded')
            ->assertJsonPath('amount', 10000);

        Queue::assertPushed(ProcessRefundJob::class);
    }

    public function test_full_refund_when_amount_not_specified(): void
    {
        Queue::fake();
        $id = $this->createSucceededPayment(5000);
        $this->mockRefundProvider();

        // Не передаём amount — должен вернуть всю сумму
        $response = $this->postJson("/api/v1/payments/{$id}/refund", [
            'reason' => 'Клиент передумал',
        ]);

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonPath('status', 'Succeeded');

        Queue::assertPushed(ProcessRefundJob::class);
    }

    // ─── Partial refund ───────────────────────────────────────────────────────

    public function test_partial_refund_keeps_succeeded_status(): void
    {
        $id = $this->createSucceededPayment(10000);
        $this->mockRefundProvider();

        $response = $this->postJson("/api/v1/payments/{$id}/refund", [
            'amount' => 3000,
        ]);

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonPath('status', 'Succeeded'); // частичный — остаётся Succeeded
    }

    public function test_two_partial_refunds_both_dispatch_jobs(): void
    {
        Queue::fake();
        $id = $this->createSucceededPayment(10000);
        $this->mockRefundProvider();

        $this->postJson("/api/v1/payments/{$id}/refund", ['amount' => 4000])
            ->assertStatus(Response::HTTP_OK)
            ->assertJsonPath('status', 'Succeeded');

        $this->postJson("/api/v1/payments/{$id}/refund", ['amount' => 6000])
            ->assertStatus(Response::HTTP_OK)
            ->assertJsonPath('status', 'Succeeded');

        // Оба рефанда создали джобы — статус изменится после обработки
        $this->assertSame(2, Queue::pushed(ProcessRefundJob::class)->count());
    }

    public function test_refund_exceeding_payment_amount_returns_409(): void
    {
        $id = $this->createSucceededPayment(10000);
        $this->mockRefundProvider();

        // Сначала частичный рефанд
        $this->postJson("/api/v1/payments/{$id}/refund", ['amount' => 8000])
            ->assertStatus(Response::HTTP_OK);

        // Теперь пытаемся вернуть больше остатка
        $response = $this->postJson("/api/v1/payments/{$id}/refund", ['amount' => 5000]);

        $response->assertStatus(Response::HTTP_CONFLICT);
    }

    // ─── Error cases ──────────────────────────────────────────────────────────

    public function test_refund_returns_404_for_unknown_payment(): void
    {
        $fakeId = PaymentId::generate()->toString();

        $response = $this->postJson("/api/v1/payments/{$fakeId}/refund");

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

        $mockProvider = $this->mock(PaymentProviderInterface::class, function ($mock): void {
            $mock->shouldReceive('name')->andReturn('yookassa');
        });
        $this->app->make(PaymentProviderRegistry::class)->register($mockProvider);

        $response = $this->postJson("/api/v1/payments/{$id}/refund");

        $response->assertStatus(Response::HTTP_CONFLICT);
    }

    public function test_refund_validates_minimum_amount(): void
    {
        $id = $this->createSucceededPayment();

        $response = $this->postJson("/api/v1/payments/{$id}/refund", ['amount' => 50]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonValidationErrors(['amount']);
    }

    // ─── Idempotency ──────────────────────────────────────────────────────────

    public function test_refund_is_idempotent_with_same_key(): void
    {
        Queue::fake();
        $id = $this->createSucceededPayment(10000);
        $key = (string) Str::uuid();
        $this->mockRefundProvider();

        $first = $this->postJson("/api/v1/payments/{$id}/refund", [], ['Idempotency-Key' => $key]);
        $first->assertStatus(Response::HTTP_OK)->assertJsonPath('status', 'Succeeded');

        // Второй запрос — идёт из кэша, джоба не создаётся повторно
        $second = $this->postJson("/api/v1/payments/{$id}/refund", [], ['Idempotency-Key' => $key]);
        $second->assertStatus(Response::HTTP_OK)
            ->assertJsonPath('status', 'Succeeded')
            ->assertJsonPath('id', $first->json('id'));

        // ProcessRefundJob должен быть создан только один раз
        $this->assertSame(1, Queue::pushed(ProcessRefundJob::class)->count());
    }

    public function test_different_idempotency_keys_create_separate_refunds(): void
    {
        Queue::fake();
        $id = $this->createSucceededPayment(10000);
        $this->mockRefundProvider();

        $this->postJson("/api/v1/payments/{$id}/refund", ['amount' => 4000], ['Idempotency-Key' => (string) Str::uuid()])
            ->assertStatus(Response::HTTP_OK)
            ->assertJsonPath('status', 'Succeeded');

        $this->postJson("/api/v1/payments/{$id}/refund", ['amount' => 6000], ['Idempotency-Key' => (string) Str::uuid()])
            ->assertStatus(Response::HTTP_OK)
            ->assertJsonPath('status', 'Succeeded');

        $this->assertSame(2, Queue::pushed(ProcessRefundJob::class)->count());
    }

    public function test_idempotency_cache_is_not_polluted_on_failed_refund(): void
    {
        $fakeId = PaymentId::generate()->toString();
        $key = (string) Str::uuid();

        $this->postJson("/api/v1/payments/{$fakeId}/refund", [], ['Idempotency-Key' => $key])
            ->assertStatus(Response::HTTP_NOT_FOUND);

        // Ключ не должен попасть в кэш при ошибке
        $this->assertNull(Cache::get("refund_idem:{$key}"));
    }
}
