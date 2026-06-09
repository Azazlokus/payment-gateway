<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Payments\Domain\Enums\RefundStatus;
use App\Payments\Infrastructure\Jobs\ProcessRefundJob;
use App\Payments\Infrastructure\Persistence\Models\Refund;
use Illuminate\Console\Command;

final class RetryFailedRefundsCommand extends Command
{
    protected $signature = 'payments:retry-refunds
        {--status=failed : Статус для повторной обработки (failed|requires_review)}
        {--limit=50 : Максимальное количество рефандов для повтора}';

    protected $description = 'Повторно отправить сбойные рефанды в очередь';

    public function handle(): int
    {
        $statusFilter = $this->option('status');
        $limit = (int) $this->option('limit');

        $status = RefundStatus::tryFrom($statusFilter);

        if ($status === null || ! in_array($status, [RefundStatus::Failed, RefundStatus::RequiresReview], true)) {
            $this->error("Invalid status: {$statusFilter}. Use 'failed' or 'requires_review'.");

            return self::FAILURE;
        }

        $refunds = Refund::where('status', $status->value)
            ->where(function ($q) {
                $q->whereNull('next_retry_at')
                    ->orWhere('next_retry_at', '<=', now());
            })
            ->limit($limit)
            ->get();

        if ($refunds->isEmpty()) {
            $this->info('No refunds to retry.');

            return self::SUCCESS;
        }

        $dispatched = 0;

        foreach ($refunds as $refund) {
            $refund->update(['status' => RefundStatus::Pending]);
            ProcessRefundJob::dispatch($refund->id);
            $dispatched++;
        }

        $this->info("Dispatched {$dispatched} refund(s) for retry.");

        return self::SUCCESS;
    }
}
