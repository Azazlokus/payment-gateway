<?php

declare(strict_types=1);

namespace Tests\Feature\Payments;

use App\Payments\Domain\ValueObjects\DisputeId;
use App\Payments\Domain\ValueObjects\PaymentId;
use App\Payments\Infrastructure\Persistence\Models\DisputeModel;
use App\Payments\Infrastructure\Persistence\Models\PaymentModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class DisputeTest extends TestCase
{
    use RefreshDatabase;

    private function createPayment(string $status = 'Succeeded'): PaymentModel
    {
        return PaymentModel::create([
            'id'              => PaymentId::generate()->toString(),
            'external_id'     => (string) Str::uuid(),
            'provider'        => 'yookassa',
            'amount'          => 50000,
            'refunded_amount' => 0,
            'currency'        => 'RUB',
            'status'          => $status,
            'description'     => 'Test payment',
            'idempotency_key' => (string) Str::uuid(),
            'metadata'        => [],
        ]);
    }

    private function createDispute(string $paymentId, string $status = 'Filed'): DisputeModel
    {
        return DisputeModel::create([
            'id'         => DisputeId::generate()->toString(),
            'payment_id' => $paymentId,
            'status'     => $status,
            'amount'     => 50000,
            'currency'   => 'RUB',
            'reason'     => 'Товар не получен',
        ]);
    }

    // ─── Создание диспута ────────────────────────────────────────────────────

    public function test_file_dispute_returns_201(): void
    {
        $payment = $this->createPayment();

        $response = $this->postJson("/api/payments/{$payment->id}/disputes", [
            'amount' => 50000,
            'reason' => 'Товар не получен',
        ]);

        $response->assertStatus(Response::HTTP_CREATED)
            ->assertJsonStructure(['id', 'payment_id', 'status', 'amount', 'currency', 'reason'])
            ->assertJsonPath('status', 'Filed')
            ->assertJsonPath('amount', 50000)
            ->assertJsonPath('payment_id', $payment->id);
    }

    public function test_file_dispute_validates_required_fields(): void
    {
        $payment = $this->createPayment();

        $this->postJson("/api/payments/{$payment->id}/disputes", [])
            ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonValidationErrors(['amount', 'reason']);
    }

    public function test_file_dispute_returns_404_for_unknown_payment(): void
    {
        $this->postJson('/api/payments/01HHHHHHHHHHHHHHHHHHHHHHH/disputes', [
            'amount' => 50000,
            'reason' => 'Test',
        ])->assertStatus(Response::HTTP_NOT_FOUND);
    }

    public function test_file_dispute_returns_404_for_invalid_payment_id(): void
    {
        $this->postJson('/api/payments/not-a-ulid/disputes', [
            'amount' => 50000,
            'reason' => 'Test',
        ])->assertStatus(Response::HTTP_NOT_FOUND);
    }

    // ─── Список диспутов ─────────────────────────────────────────────────────

    public function test_list_disputes_for_payment(): void
    {
        $payment = $this->createPayment();
        $this->createDispute($payment->id);
        $this->createDispute($payment->id, 'Won');

        $response = $this->getJson("/api/payments/{$payment->id}/disputes");

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure(['data'])
            ->assertJsonCount(2, 'data');
    }

    public function test_list_disputes_returns_empty_array_when_none(): void
    {
        $payment = $this->createPayment();

        $this->getJson("/api/payments/{$payment->id}/disputes")
            ->assertStatus(Response::HTTP_OK)
            ->assertJsonCount(0, 'data');
    }

    // ─── Просмотр диспута ────────────────────────────────────────────────────

    public function test_show_dispute_returns_dispute(): void
    {
        $payment = $this->createPayment();
        $dispute = $this->createDispute($payment->id);

        $this->getJson("/api/disputes/{$dispute->id}")
            ->assertStatus(Response::HTTP_OK)
            ->assertJsonPath('id', $dispute->id)
            ->assertJsonPath('status', 'Filed');
    }

    public function test_show_dispute_returns_404_for_unknown(): void
    {
        $this->getJson('/api/disputes/01HHHHHHHHHHHHHHHHHHHHHHH')
            ->assertStatus(Response::HTTP_NOT_FOUND);
    }

    // ─── Разрешение диспута ──────────────────────────────────────────────────

    public function test_resolve_dispute_as_won(): void
    {
        $payment = $this->createPayment();
        $dispute = $this->createDispute($payment->id);

        $this->postJson("/api/disputes/{$dispute->id}/resolve", [
            'resolution' => 'Won',
            'note'       => 'Клиент прав',
        ])
            ->assertStatus(Response::HTTP_OK)
            ->assertJsonPath('status', 'Won')
            ->assertJsonPath('note', 'Клиент прав');
    }

    public function test_resolve_dispute_as_lost(): void
    {
        $payment = $this->createPayment();
        $dispute = $this->createDispute($payment->id);

        $this->postJson("/api/disputes/{$dispute->id}/resolve", [
            'resolution' => 'Lost',
        ])
            ->assertStatus(Response::HTTP_OK)
            ->assertJsonPath('status', 'Lost');
    }

    public function test_resolve_already_resolved_dispute_returns_409(): void
    {
        $payment = $this->createPayment();
        $dispute = $this->createDispute($payment->id, 'Won');

        $this->postJson("/api/disputes/{$dispute->id}/resolve", [
            'resolution' => 'Lost',
        ])->assertStatus(Response::HTTP_CONFLICT);
    }

    public function test_resolve_validates_resolution_field(): void
    {
        $payment = $this->createPayment();
        $dispute = $this->createDispute($payment->id);

        $this->postJson("/api/disputes/{$dispute->id}/resolve", [
            'resolution' => 'InvalidValue',
        ])->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonValidationErrors(['resolution']);
    }

    public function test_resolve_returns_404_for_unknown_dispute(): void
    {
        $this->postJson('/api/disputes/01HHHHHHHHHHHHHHHHHHHHHHH/resolve', [
            'resolution' => 'Won',
        ])->assertStatus(Response::HTTP_NOT_FOUND);
    }
}
