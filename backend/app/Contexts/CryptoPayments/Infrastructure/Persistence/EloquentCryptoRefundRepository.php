<?php

declare(strict_types=1);

namespace App\Contexts\CryptoPayments\Infrastructure\Persistence;

use App\Contexts\CryptoPayments\Domain\Aggregates\CryptoRefundRequest;
use App\Contexts\CryptoPayments\Domain\Contracts\CryptoRefundRepositoryInterface;
use App\Contexts\CryptoPayments\Domain\Enums\CryptoRefundStatus;
use App\Contexts\CryptoPayments\Domain\ValueObjects\CryptoRefundId;
use App\Contexts\CryptoPayments\Infrastructure\Persistence\Models\CryptoDepositEventModel;
use App\Contexts\CryptoPayments\Infrastructure\Persistence\Models\CryptoRefundModel;

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

        // События возврата привязаны к депозиту (его агрегат-корню), пишем в общее
        // крипто-хранилище событий. depositId — валидная запись crypto_deposits.
        foreach ($refund->pullDomainEvents() as $event) {
            CryptoDepositEventModel::create([
                'deposit_id' => $refund->depositId(),
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
