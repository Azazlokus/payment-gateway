<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Очищает idempotency_key у платежей в терминальных статусах старше 90 дней.
 *
 * После очистки эти ключи больше не будут блокировать создание новых платежей
 * с теми же ключами, что безопасно — платёж уже завершён.
 */
final class PruneIdempotencyKeysCommand extends Command
{
    protected $signature   = 'payments:prune-idempotency-keys {--days=90 : Возраст платежей в днях}';

    protected $description = 'Очищает idempotency_key у завершённых платежей старше N дней';

    public function handle(): int
    {
        $days = (int) $this->option('days');

        $cutoff = now()->subDays($days);

        $count = DB::table('payments')
            ->whereIn('status', ['Succeeded', 'Cancelled', 'Refunded'])
            ->where('created_at', '<', $cutoff)
            ->whereNotNull('idempotency_key')
            ->update(['idempotency_key' => null]);

        $this->info("Pruned idempotency_key from {$count} payments older than {$days} days.");

        return self::SUCCESS;
    }
}
