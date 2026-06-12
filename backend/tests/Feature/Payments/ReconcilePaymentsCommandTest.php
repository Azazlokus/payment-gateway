<?php

declare(strict_types=1);

namespace Tests\Feature\Payments;

use App\Contexts\Payments\Application\Bus\CommandBus;
use App\Contexts\Payments\Application\Commands\SyncPayment\SyncPaymentCommand;
use App\Contexts\Payments\Domain\ValueObjects\PaymentId;
use App\Contexts\Payments\Infrastructure\Persistence\Models\PaymentModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Тестовый шпион для CommandBus.
 * Перехватывает вызовы dispatch() и записывает команды для проверки.
 */
class CommandBusSpy extends CommandBus
{
    /** @var list<object> */
    public array $dispatched = [];

    public ?\Throwable $exception = null;

    public function __construct()
    {
        // Не вызываем parent — Pipeline не нужен в шпионе
    }

    public function dispatch(object $command): mixed
    {
        if ($this->exception !== null) {
            throw $this->exception;
        }

        $this->dispatched[] = $command;

        return null;
    }

    public function wasDispatched(): bool
    {
        return $this->dispatched !== [];
    }
}

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

    /** Подменяет CommandBus на тестовый шпион */
    private function spyCommandBus(): CommandBusSpy
    {
        $spy = new CommandBusSpy;
        $this->app->instance(CommandBus::class, $spy);

        return $spy;
    }

    public function test_reconcile_syncs_stale_pending_payments(): void
    {
        $stale = $this->makePayment('Pending', 3); // 3 часа назад — старше --hours=2
        $spy = $this->spyCommandBus();

        $this->artisan('payments:reconcile', ['--hours' => 2])
            ->expectsOutputToContain('1 Pending payments')
            ->assertSuccessful();

        $this->assertCount(1, $spy->dispatched);
        $this->assertInstanceOf(SyncPaymentCommand::class, $spy->dispatched[0]);
        $this->assertSame($stale->id, $spy->dispatched[0]->paymentId);
    }

    public function test_reconcile_ignores_recent_pending_payments(): void
    {
        $this->makePayment('Pending', 1); // 1 час назад — моложе --hours=2
        $spy = $this->spyCommandBus();

        $this->artisan('payments:reconcile', ['--hours' => 2])
            ->expectsOutputToContain('0 Pending payments')
            ->assertSuccessful();

        $this->assertFalse($spy->wasDispatched());
    }

    public function test_reconcile_ignores_non_pending_payments(): void
    {
        $this->makePayment('Succeeded', 5);
        $this->makePayment('Cancelled', 5);
        $this->makePayment('Refunded', 5);

        $spy = $this->spyCommandBus();

        $this->artisan('payments:reconcile', ['--hours' => 2])
            ->expectsOutputToContain('0 Pending payments')
            ->assertSuccessful();

        $this->assertFalse($spy->wasDispatched());
    }

    public function test_reconcile_dry_run_does_not_sync(): void
    {
        $this->makePayment('Pending', 5);
        $spy = $this->spyCommandBus();

        $this->artisan('payments:reconcile', ['--hours' => 2, '--dry-run' => true])
            ->expectsOutputToContain('dry-run')
            ->assertSuccessful();

        $this->assertFalse($spy->wasDispatched());
    }

    public function test_reconcile_filters_by_provider(): void
    {
        $yookassa = $this->makePayment('Pending', 5);
        $yookassa->update(['provider' => 'yookassa']);

        $robokassa = $this->makePayment('Pending', 5);
        $robokassa->update(['provider' => 'robokassa']);

        $spy = $this->spyCommandBus();

        $this->artisan('payments:reconcile', ['--hours' => 2, '--provider' => 'yookassa'])
            ->assertSuccessful();

        $dispatched = array_map(fn ($cmd) => $cmd->paymentId, $spy->dispatched);
        $this->assertContains($yookassa->id, $dispatched);
        $this->assertNotContains($robokassa->id, $dispatched);
    }

    public function test_reconcile_reports_failure_exit_code_when_sync_throws(): void
    {
        $this->makePayment('Pending', 5);

        $spy = $this->spyCommandBus();
        $spy->exception = new \RuntimeException('provider timeout');

        $this->artisan('payments:reconcile', ['--hours' => 2])
            ->assertFailed();
    }
}
