<?php

declare(strict_types=1);

namespace App\CryptoPayments\Infrastructure\Persistence;

use App\CryptoPayments\Domain\Aggregates\CryptoRefundRequest;
use App\CryptoPayments\Domain\Contracts\CryptoRefundRepositoryInterface;
use App\CryptoPayments\Domain\Enums\CryptoRefundStatus;
use App\CryptoPayments\Domain\ValueObjects\CryptoRefundId;
use App\CryptoPayments\Infrastructure\Persistence\Models\CryptoRefundModel;
use App\Payments\Infrastructure\Persistence\Models\PaymentEventModel;

final class EloquentCryptoRefundRepository implements CryptoRefundRepositoryInterface
{
    public function save(CryptoRefundRequest $refund): void
    {
        CryptoRefundModel::updateOrCreate(
            ['id' => $refund->id()->toString()],
            [
                'deposit_id' => $refund->depositId(),
                'to_address' => $refund->toAddress()->toString(),
                'amount_units' => $refund->amount()->units(),
                'asset' => $refund->asset()->value,
                'status' => $refund->status()->value,
                'tx_hash' => $refund->txHash()?->toString(),
                'failure_reason' => $refund->failureReason(),
            ]
        );

        foreach ($refund->pullDomainEvents() as $event) {
            PaymentEventModel::create([
                'payment_id' => $refund->depositId(),
                'event_id' => $event->eventId,
                'event_name' => $event->eventName(),
                'event_data' => $event->toArray(),
                'occurred_at' => $event->occurredAt,
            ]);
        }
    }

    public function findById(CryptoRefundId $id): ?CryptoRefundRequest
    {
        $model = CryptoRefundModel::find($id->toString());

        return $model ? $this->hydrate($model) : null;
    }

    public function existsForDeposit(string $depositId): bool
    {
        return CryptoRefundModel::where('deposit_id', $depositId)
            ->whereIn('status', [CryptoRefundStatus::Pending->value, CryptoRefundStatus::Broadcasting->value])
            ->exists();
    }

    /** @return list<CryptoRefundRequest> */
    public function findPending(): array
    {
        return array_values(
            CryptoRefundModel::where('status', CryptoRefundStatus::Pending->value)
                ->orderBy('created_at')
                ->get()
                ->map(fn (CryptoRefundModel $m) => $this->hydrate($m))
                ->all(),
        );
    }

    private function hydrate(CryptoRefundModel $model): CryptoRefundRequest
    {
        return CryptoRefundRequest::restore(
            id: (string) $model->id,
            depositId: (string) $model->deposit_id,
            toAddress: (string) $model->to_address,
            amountUnits: (int) $model->amount_units,
            asset: $model->asset->value,
            status: $model->status->value,
            txHash: isset($model->tx_hash) ? (string) $model->tx_hash : null,
            failureReason: isset($model->failure_reason) ? (string) $model->failure_reason : null,
        );
    }
}
