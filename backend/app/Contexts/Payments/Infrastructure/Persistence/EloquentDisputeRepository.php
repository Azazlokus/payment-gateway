<?php

declare(strict_types=1);

namespace App\Contexts\Payments\Infrastructure\Persistence;

use App\Contexts\Payments\Domain\Aggregates\Dispute;
use App\Contexts\Payments\Domain\Contracts\DisputeRepositoryInterface;
use App\Contexts\Payments\Domain\ValueObjects\DisputeId;
use App\Contexts\Payments\Domain\ValueObjects\Money;
use App\Contexts\Payments\Domain\ValueObjects\PaymentId;
use App\Contexts\Payments\Infrastructure\Persistence\Models\DisputeModel;
use App\Contexts\Payments\Infrastructure\Persistence\Models\PaymentEventModel;

final class EloquentDisputeRepository implements DisputeRepositoryInterface
{
    public function save(Dispute $dispute): void
    {
        DisputeModel::updateOrCreate(
            ['id' => $dispute->id()->toString()],
            [
                'payment_id' => $dispute->paymentId()->toString(),
                'status' => $dispute->status()->value,
                'amount' => $dispute->amount()->amount(),
                'currency' => $dispute->amount()->currency()->value,
                'reason' => $dispute->reason(),
                'note' => $dispute->note(),
            ]
        );

        foreach ($dispute->pullDomainEvents() as $event) {
            PaymentEventModel::create([
                'payment_id' => $dispute->paymentId()->toString(),
                'event_id' => $event->eventId,
                'event_name' => $event->eventName(),
                'event_data' => $event->toArray(),
                'occurred_at' => $event->occurredAt,
            ]);
        }
    }

    public function findById(DisputeId $id): ?Dispute
    {
        $model = DisputeModel::find($id->toString());

        return $model ? $this->hydrate($model) : null;
    }

    /** @return Dispute[] */
    public function findByPaymentId(PaymentId $paymentId): array
    {
        return DisputeModel::where('payment_id', $paymentId->toString())
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($m) => $this->hydrate($m))
            ->all();
    }

    private function hydrate(DisputeModel $model): Dispute
    {
        return Dispute::restore(
            id: DisputeId::fromString($model->id),
            paymentId: PaymentId::fromString($model->payment_id),
            status: $model->status,
            amount: Money::ofRub($model->amount),
            reason: $model->reason,
            note: $model->note,
        );
    }
}
