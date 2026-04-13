<?php

declare(strict_types=1);

namespace App\Payments\Infrastructure\Persistence;

use App\Payments\Domain\Aggregates\Payment;
use App\Payments\Domain\Contracts\PaymentRepositoryInterface;
use App\Payments\Domain\Enums\PaymentStatus;
use App\Payments\Domain\ValueObjects\ExternalId;
use App\Payments\Domain\ValueObjects\Money;
use App\Payments\Domain\ValueObjects\PaymentId;
use App\Payments\Infrastructure\Persistence\Models\PaymentEventModel;
use App\Payments\Infrastructure\Persistence\Models\PaymentModel;

final class EloquentPaymentRepository implements PaymentRepositoryInterface
{
    public function save(Payment $payment): void
    {
        PaymentModel::updateOrCreate(
            ['id' => $payment->id()->toString()],
            [
                'external_id' => $payment->externalId()?->toString(),
                'payment_method_id' => $payment->paymentMethodId(),
                'provider' => $payment->provider(),
                'amount' => $payment->amount()->amount(),
                'refunded_amount' => $payment->refundedAmountKopecks(),
                'currency' => $payment->amount()->currency()->value,
                'description' => $payment->description(),
                'status' => $payment->status()->value,
                'confirmation_url' => $payment->confirmationUrl(),
                'idempotency_key' => $payment->idempotencyKey(),
                'metadata' => $payment->metadata(),
            ]
        );

        foreach ($payment->pullDomainEvents() as $event) {
            PaymentEventModel::create([
                'payment_id' => $payment->id()->toString(),
                'event_id' => $event->eventId,
                'event_name' => $event->eventName(),
                'event_data' => $event->toArray(),
                'occurred_at' => $event->occurredAt,
            ]);
        }
    }

    public function findById(PaymentId $id): ?Payment
    {
        $model = PaymentModel::find($id->toString());

        return $model ? $this->hydrate($model) : null;
    }

    public function findByIdempotencyKey(string $key): ?Payment
    {
        $model = PaymentModel::where('idempotency_key', $key)->first();

        return $model ? $this->hydrate($model) : null;
    }

    public function findByExternalId(string $externalId): ?Payment
    {
        $model = PaymentModel::where('external_id', $externalId)->first();

        return $model ? $this->hydrate($model) : null;
    }

    public function paginate(int $perPage, int $page, array $filters): array
    {
        $query = PaymentModel::query()->orderByDesc('created_at');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['from_date'])) {
            $query->whereDate('created_at', '>=', $filters['from_date']);
        }

        if (! empty($filters['to_date'])) {
            $query->whereDate('created_at', '<=', $filters['to_date']);
        }

        $paginator = $query->paginate(perPage: $perPage, page: $page);

        return [
            'data' => $paginator->getCollection()->map(fn ($m) => $this->hydrate($m))->all(),
            'total' => $paginator->total(),
            'per_page' => $paginator->perPage(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
        ];
    }

    private function hydrate(PaymentModel $model): Payment
    {
        return Payment::restore(
            id: PaymentId::fromString($model->id),
            amount: Money::ofRub($model->amount),
            status: $model->status instanceof PaymentStatus
                                       ? $model->status
                                       : PaymentStatus::from($model->status),
            description: $model->description,
            provider: $model->provider,
            idempotencyKey: $model->idempotency_key,
            externalId: $model->external_id
                                       ? ExternalId::fromString($model->external_id)
                                       : null,
            confirmationUrl: $model->confirmation_url,
            metadata: $model->metadata ?? [],
            paymentMethodId: $model->payment_method_id,
            refundedAmountKopecks: (int) ($model->refunded_amount ?? 0),
        );
    }
}
