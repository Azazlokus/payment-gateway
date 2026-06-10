<?php

declare(strict_types=1);

namespace Tests\Feature\Payments;

use App\Payments\Application\Bus\CommandBus;
use App\Payments\Application\Commands\SyncPayment\SyncPaymentCommand;
use App\Payments\Domain\ValueObjects\PaymentId;
use App\Payments\Infrastructure\Persistence\Models\PaymentModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Mockery\MockInterface;
use Tests\TestCase;

class ReconcilePaymentsCommandTest extends TestCase
{
    use RefreshDatabase;

    private function makePayment(string $status, int $hoursAgo = 0): PaymentModel
    {
        $model = PaymentModel::create([
            'id' => PaymentId::generate()->toString(),
            'external_id' => (string) Str::uuid(),
            'provider' => 'yookassa',
            'amount' => 10000,
            'refunded_amount' => 0,
            'currency' => 'RUB',
            'status' => $status,
            'description' => 'Test',
            'idempotency_key' => (string) Str::uuid(),
            'metadata' => [],
        ]);

        if ($hoursAgo > 0) {
            $model->created_at = now()->subHours($hoursAgo);
            $model->updated_at = now()->subHours($hoursAgo);
            $model->saveQuietly();
        }

        return $model;
    }

    public function test_reconcile_syncs_stale_pending_payments(): void
    {
        $stale = $this->makePayment('Pending', 3); // 3 часа назад — старше --hours=2

        $this->mock(CommandBus::class, function (MockInterface $mock) use ($stale) {
            $mock->shouldReceive('dispatch')
                ->once()
                ->withArgs(fn ($cmd) => $cmd instanceof SyncPaymentCommand && $cmd->paymentId === $stale->id);
        });

        $this->artisan('payments:reconcile', ['--hours' => 2])
            ->expectsOutputToContain('1 Pending payments')
            ->assertSuccessful();
    }

    public function test_reconcile_ignores_recent_pending_payments(): void
    {
        $this->makePayment('Pending', 1); // 1 час назад — моложе --hours=2

        $this->mock(CommandBus::class, function (MockInterface $mock) {
            $mock->shouldNotReceive('dispatch');
        });

        $this->artisan('payments:reconcile', ['--hours' => 2])
            ->expectsOutputToContain('0 Pending payments')
            ->assertSuccessful();
    }

    public function test_reconcile_ignores_non_pending_payments(): void
    {
        $this->makePayment('Succeeded', 5);
        $this->makePayment('Cancelled', 5);
        $this->makePayment('Refunded', 5);

        $this->mock(CommandBus::class, function (MockInterface $mock) {
            $mock->shouldNotReceive('dispatch');
        });

        $this->artisan('payments:reconcile', ['--hours' => 2])
            ->expectsOutputToContain('0 Pending payments')
            ->assertSuccessful();
    }

    public function test_reconcile_dry_run_does_not_sync(): void
    {
        $this->makePayment('Pending', 5);

        $this->mock(CommandBus::class, function (MockInterface $mock) {
            $mock->shouldNotReceive('dispatch');
        });

        $this->artisan('payments:reconcile', ['--hours' => 2, '--dry-run' => true])
            ->expectsOutputToContain('dry-run')
            ->assertSuccessful();
    }

    public function test_reconcile_filters_by_provider(): void
    {
        $yookassa = $this->makePayment('Pending', 5);
        $yookassa->update(['provider' => 'yookassa']);

        $robokassa = $this->makePayment('Pending', 5);
        $robokassa->update(['provider' => 'robokassa']);

        $synced = [];
        $this->mock(CommandBus::class, function (MockInterface $mock) use (&$synced) {
            $mock->shouldReceive('dispatch')
                ->andReturnUsing(function ($cmd) use (&$synced) {
                    $synced[] = $cmd->paymentId;
                });
        });

        $this->artisan('payments:reconcile', ['--hours' => 2, '--provider' => 'yookassa'])
            ->assertSuccessful();

        $this->assertContains($yookassa->id, $synced);
        $this->assertNotContains($robokassa->id, $synced);
    }

    public function test_reconcile_reports_failure_exit_code_when_sync_throws(): void
    {
        $this->makePayment('Pending', 5);

        $this->mock(CommandBus::class, function (MockInterface $mock) {
            $mock->shouldReceive('dispatch')->andThrow(new \RuntimeException('provider timeout'));
        });

        $this->artisan('payments:reconcile', ['--hours' => 2])
            ->assertFailed();
    }
}
