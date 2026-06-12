<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contexts\Payments\Application\Bus\CommandBus;
use App\Contexts\Payments\Application\Commands\SyncPayment\SyncPaymentCommand;
use App\Contexts\Payments\Domain\Enums\PaymentStatus;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Сверяет Pending-платежи с провайдерами и обновляет их статусы.
 *
 * Нужна для ситуаций когда вебхук не дошёл (сеть, рестарт horizon и т.д.)
 * и платёж завис в Pending. Команда проходит по всем «зависшим» платежам
 * и делает SyncPaymentCommand — запрашивает актуальный статус у провайдера.
 *
 * Запускается: make artisan CMD="payments:reconcile" или через scheduler.
 */
final class ReconcilePaymentsCommand extends Command
{
    protected $signature = 'payments:reconcile
        {--hours=2        : Синхронизировать Pending-платежи старше N часов}
        {--provider=      : Ограничить конкретным провайдером (yookassa, robokassa, ...)}
        {--dry-run        : Только показать кандидатов, не синхронизировать}
        {--batch=50       : Размер батча}';

    protected $description = 'Сверяет Pending-платежи с провайдерами и актуализирует статусы';

    public function __construct(private readonly CommandBus $bus)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $hours = (int) $this->option('hours');
        $provider = $this->option('provider') ?: null;
        $dryRun = (bool) $this->option('dry-run');
        $batch = (int) $this->option('batch');

        $cutoff = now()->subHours($hours);

        $query = DB::table('payments')
            ->select(['id', 'provider'])
            ->where('status', PaymentStatus::Pending->value)
            ->where('created_at', '<', $cutoff)
            ->whereNull('deleted_at')
            ->orderBy('created_at');

        if ($provider !== null) {
            $query->where('provider', $provider);
        }

        $total = (clone $query)->count();
        $synced = 0;
        $failed = 0;

        $this->info("Found {$total} Pending payments older than {$hours}h".($provider ? " (provider: {$provider})" : '').'.');

        if ($total === 0) {
            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->warn('[dry-run] No changes made.');

            return self::SUCCESS;
        }

        $query->chunkById($batch, function ($chunk) use (&$synced, &$failed) {
            foreach ($chunk as $row) {
                try {
                    $this->bus->dispatch(new SyncPaymentCommand(paymentId: $row->id));
                    $synced++;
                } catch (\Throwable $e) {
                    $failed++;
                    Log::warning('payments:reconcile sync failed', [
                        'payment_id' => $row->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        });

        $this->info("Reconciled: {$synced} synced, {$failed} failed.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
