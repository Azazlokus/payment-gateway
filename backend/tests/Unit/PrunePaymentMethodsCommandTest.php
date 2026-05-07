<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Payments\Domain\ValueObjects\PaymentId;
use App\Payments\Infrastructure\Persistence\Models\PaymentModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PrunePaymentMethodsCommandTest extends TestCase
{
    use RefreshDatabase;

    private function makePayment(string $status, ?string $paymentMethodId, int $daysAgo = 0): PaymentModel
    {
        $model = PaymentModel::create([
            'id'                => PaymentId::generate()->toString(),
            'external_id'       => (string) Str::uuid(),
            'provider'          => 'yookassa',
            'amount'            => 10000,
            'refunded_amount'   => 0,
            'currency'          => 'RUB',
            'status'            => $status,
            'description'       => 'Test',
            'idempotency_key'   => (string) Str::uuid(),
            'payment_method_id' => $paymentMethodId,
            'metadata'          => [],
        ]);

        if ($daysAgo > 0) {
            $model->created_at = now()->subDays($daysAgo);
            $model->updated_at = now()->subDays($daysAgo);
            $model->saveQuietly();
        }

        return $model;
    }

    public function test_prunes_old_terminal_payments_with_method_id(): void
    {
        $old = $this->makePayment('Succeeded', 'pm_abc123', 400);

        $this->artisan('payments:prune-payment-methods', ['--days' => 365])
            ->assertSuccessful();

        $this->assertNull($old->fresh()?->payment_method_id);
    }

    public function test_does_not_prune_recent_payments(): void
    {
        $recent = $this->makePayment('Succeeded', 'pm_abc123', 10);

        $this->artisan('payments:prune-payment-methods', ['--days' => 365])
            ->assertSuccessful();

        $this->assertSame('pm_abc123', $recent->fresh()?->payment_method_id);
    }

    public function test_does_not_prune_pending_payments(): void
    {
        $pending = $this->makePayment('Pending', 'pm_abc123', 400);

        $this->artisan('payments:prune-payment-methods', ['--days' => 365])
            ->assertSuccessful();

        $this->assertSame('pm_abc123', $pending->fresh()?->payment_method_id);
    }

    public function test_does_not_touch_payments_without_method_id(): void
    {
        $payment = $this->makePayment('Succeeded', null, 400);

        $this->artisan('payments:prune-payment-methods', ['--days' => 365])
            ->assertSuccessful();

        $this->assertNull($payment->fresh()?->payment_method_id);
    }

    public function test_prunes_all_terminal_statuses(): void
    {
        $succeeded = $this->makePayment('Succeeded', 'pm_1', 400);
        $cancelled = $this->makePayment('Cancelled', 'pm_2', 400);
        $refunded  = $this->makePayment('Refunded',  'pm_3', 400);

        $this->artisan('payments:prune-payment-methods', ['--days' => 365])
            ->assertSuccessful();

        $this->assertNull($succeeded->fresh()?->payment_method_id);
        $this->assertNull($cancelled->fresh()?->payment_method_id);
        $this->assertNull($refunded->fresh()?->payment_method_id);
    }

    public function test_respects_custom_days_option(): void
    {
        // 40 дней назад — должен попасть под --days=30
        $old = $this->makePayment('Succeeded', 'pm_old', 40);
        // 10 дней назад — не попадёт
        $recent = $this->makePayment('Succeeded', 'pm_new', 10);

        $this->artisan('payments:prune-payment-methods', ['--days' => 30])
            ->assertSuccessful();

        $this->assertNull($old->fresh()?->payment_method_id);
        $this->assertSame('pm_new', $recent->fresh()?->payment_method_id);
    }

    public function test_outputs_count_of_pruned_records(): void
    {
        $this->makePayment('Succeeded', 'pm_1', 400);
        $this->makePayment('Succeeded', 'pm_2', 400);

        $this->artisan('payments:prune-payment-methods', ['--days' => 365])
            ->expectsOutputToContain('2')
            ->assertSuccessful();
    }

    public function test_reports_nothing_to_prune_when_table_is_empty(): void
    {
        $this->artisan('payments:prune-payment-methods', ['--days' => 365])
            ->expectsOutputToContain('No payment_method_id entries to prune')
            ->assertSuccessful();
    }
}
