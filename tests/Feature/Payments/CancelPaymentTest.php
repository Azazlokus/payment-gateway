<?php

declare(strict_types=1);

namespace Tests\Feature\Payments;

use App\Payments\Domain\Contracts\PaymentProviderInterface;
use App\Payments\Domain\Contracts\ProviderResponse;
use App\Payments\Domain\ValueObjects\ExternalId;
use App\Payments\Domain\ValueObjects\PaymentId;
use App\Payments\Infrastructure\Persistence\Models\PaymentModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class CancelPaymentTest extends TestCase
{
    use RefreshDatabase;

    private string $externalId = '22d65900-000f-5000-a000-10d000000001';

    private function createPayment(string $status = 'Pending', int $amountKopecks = 10000): string
    {
        $id = PaymentId::generate()->toString();

        PaymentModel::create([
            'id' => $id,
            'external_id' => $status !== 'Pending' ? $this->externalId : null,
            'provider' => 'yookassa',
            'amount' => $amountKopecks,
            'refunded_amount' => 0,
            'currency' => 'RUB',
            'status' => $status,
            'description' => 'Test payment',
            'idempotency_key' => (string) Str::uuid(),
            'metadata' => [],
        ]);

        return $id;
    }

    // ─── Cancel ───────────────────────────────────────────────────────────────

    public function test_cancel_pending_payment_succeeds(): void
    {
        $id = $this->createPayment('Pending');

        $this->postJson("/api/payments/{$id}/cancel")
            ->assertStatus(Response::HTTP_OK)
            ->assertJsonPath('status', 'Cancelled');
    }

    public function test_cancel_with_reason(): void
    {
        $id = $this->createPayment('Pending');

        $this->postJson("/api/payments/{$id}/cancel", ['reason' => 'Отменено по запросу клиента'])
            ->assertStatus(Response::HTTP_OK)
            ->assertJsonPath('status', 'Cancelled');
    }

    public function test_cancel_succeeded_payment_returns_409(): void
    {
        $id = $this->createPayment('Succeeded');

        $this->postJson("/api/payments/{$id}/cancel")
            ->assertStatus(Response::HTTP_CONFLICT);
    }

    public function test_cancel_refunded_payment_returns_409(): void
    {
        $id = $this->createPayment('Refunded');

        $this->postJson("/api/payments/{$id}/cancel")
            ->assertStatus(Response::HTTP_CONFLICT);
    }

    public function test_cancel_cancelled_payment_returns_409(): void
    {
        $id = $this->createPayment('Cancelled');

        $this->postJson("/api/payments/{$id}/cancel")
            ->assertStatus(Response::HTTP_CONFLICT);
    }

    public function test_cancel_unknown_payment_returns_404(): void
    {
        $fakeId = PaymentId::generate()->toString();

        $this->postJson("/api/payments/{$fakeId}/cancel")
            ->assertStatus(Response::HTTP_NOT_FOUND);
    }

    // ─── Show ─────────────────────────────────────────────────────────────────

    public function test_show_returns_payment_by_id(): void
    {
        $id = $this->createPayment('Succeeded');

        $this->getJson("/api/payments/{$id}")
            ->assertStatus(Response::HTTP_OK)
            ->assertJsonPath('id', $id)
            ->assertJsonPath('status', 'Succeeded')
            ->assertJsonPath('amount', 10000)
            ->assertJsonPath('currency', 'RUB');
    }

    public function test_show_returns_404_for_non_existent_payment(): void
    {
        $fakeId = PaymentId::generate()->toString();

        $this->getJson("/api/payments/{$fakeId}")
            ->assertStatus(Response::HTTP_NOT_FOUND)
            ->assertJsonPath('error', 'not_found');
    }

    public function test_show_returns_404_for_invalid_id_format(): void
    {
        $this->getJson('/api/payments/not-a-ulid')
            ->assertStatus(Response::HTTP_NOT_FOUND);
    }

    // ─── Sync ─────────────────────────────────────────────────────────────────

    public function test_sync_updates_payment_status_from_provider(): void
    {
        $id = $this->createPayment('Pending');

        // Добавляем external_id чтобы sync пошёл в провайдер
        PaymentModel::where('id', $id)->update(['external_id' => $this->externalId]);

        $this->mock(PaymentProviderInterface::class, function ($mock) {
            $mock->shouldReceive('name')->andReturn('yookassa');
            $mock->shouldReceive('getPayment')->andReturn(new ProviderResponse(
                externalId: ExternalId::fromString($this->externalId),
                confirmationUrl: '',
                status: 'succeeded',
            ));
        });

        $this->postJson("/api/payments/{$id}/sync")
            ->assertStatus(Response::HTTP_OK)
            ->assertJsonPath('status', 'Succeeded');
    }

    public function test_sync_returns_current_state_when_no_external_id(): void
    {
        // Pending без external_id — sync возвращает текущее состояние, не идёт в провайдер
        $id = $this->createPayment('Pending');

        $this->mock(PaymentProviderInterface::class, function ($mock) {
            $mock->shouldReceive('name')->andReturn('yookassa');
            $mock->shouldNotReceive('getPayment');
        });

        $this->postJson("/api/payments/{$id}/sync")
            ->assertStatus(Response::HTTP_OK)
            ->assertJsonPath('status', 'Pending');
    }

    public function test_sync_returns_404_for_unknown_payment(): void
    {
        $fakeId = PaymentId::generate()->toString();

        $this->mock(PaymentProviderInterface::class, function ($mock) {
            $mock->shouldReceive('name')->andReturn('yookassa');
        });

        $this->postJson("/api/payments/{$fakeId}/sync")
            ->assertStatus(Response::HTTP_NOT_FOUND);
    }
}
