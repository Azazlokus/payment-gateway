<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Удаляет payment_method_id у платежей в терминальных статусах старше N дней.
 *
 * YooKassa хранит сохранённые методы на своей стороне — нам не нужно
 * бесконечно хранить их ID в БД. После очистки рекуррентные платежи
 * с использованием этих методов станут невозможны, что ожидаемо для
 * старых завершённых транзакций.
 */
final class PrunePaymentMethodsCommand extends Command
{
    protected $signature = 'payments:prune-payment-methods {--days=365 : Возраст платежей в днях}';

    protected $description = 'Очищает payment_method_id у завершённых платежей старше N дней';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $cutoff = now()->subDays($days);

        // Сначала считаем, чтобы показать информативный вывод
        $total = DB::table('payments')
            ->whereIn('status', ['Succeeded', 'Cancelled', 'Refunded'])
            ->where('created_at', '<', $cutoff)
            ->whereNotNull('payment_method_id')
            ->count();

        if ($total === 0) {
            $this->info("No payment_method_id entries to prune (threshold: {$days} days).");

            return self::SUCCESS;
        }

        // Батчевое обновление, чтобы не блокировать таблицу большим UPDATE
        $pruned = 0;

        DB::table('payments')
            ->whereIn('status', ['Succeeded', 'Cancelled', 'Refunded'])
            ->where('created_at', '<', $cutoff)
            ->whereNotNull('payment_method_id')
            ->orderBy('id')
            ->chunkById(500, function ($chunk) use (&$pruned): void {
                $ids = $chunk->pluck('id')->all();

                $pruned += DB::table('payments')
                    ->whereIn('id', $ids)
                    ->update(['payment_method_id' => null]);
            });

        $this->info("Pruned payment_method_id from {$pruned} payments older than {$days} days.");

        return self::SUCCESS;
    }
}
