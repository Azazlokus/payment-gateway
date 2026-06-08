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
                'captured_amount' => $payment->capturedAmountKopecks(),
                'currency' => $payment->amount()->currency()->value,
                'description' => $payment->description(),
                'status' => $payment->status()->value,
                'confirmation_url' => $payment->confirmationUrl(),
                'three_ds_required' => $payment->threeDsRequired(),
                'three_ds_challenge_url' => $payment->threeDsChallengeUrl(),
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

    /** @param array<string, mixed> $filters
     *  @return array{data: Payment[], per_page: int, next_cursor: string|null, prev_cursor: string|null} */
    public function cursorPaginate(int $perPage, ?string $cursor, array $filters): array
    {
        // ORDER BY created_at + id обеспечивает уникальность курсора
        // (два платежа могут иметь одинаковый created_at)
        $query = PaymentModel::query()
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['provider'])) {
            $query->where('provider', $filters['provider']);
        }

        if (! empty($filters['from_date'])) {
            $query->whereDate('created_at', '>=', $filters['from_date']);
        }

        if (! empty($filters['to_date'])) {
            $query->whereDate('created_at', '<=', $filters['to_date']);
        }

        $paginator = $query->cursorPaginate(perPage: $perPage, cursor: $cursor);

        return [
            'data'        => $paginator->getCollection()->map(fn ($m) => $this->hydrate($m))->all(),
            'per_page'    => $paginator->perPage(),
            'next_cursor' => $paginator->nextCursor()?->encode(),
            'prev_cursor' => $paginator->previousCursor()?->encode(),
        ];
    }

    /** @param array<string, mixed> $filters
     *  @return iterable<Payment> */
    public function cursor(array $filters): iterable
    {
        $query = PaymentModel::query()->orderByDesc('created_at');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['provider'])) {
            $query->where('provider', $filters['provider']);
        }

        if (! empty($filters['from_date'])) {
            $query->whereDate('created_at', '>=', $filters['from_date']);
        }

        if (! empty($filters['to_date'])) {
            $query->whereDate('created_at', '<=', $filters['to_date']);
        }

        foreach ($query->cursor() as $model) {
            yield $this->hydrate($model);
        }
    }

    private function hydrate(PaymentModel $model): Payment
    {
        return Payment::restore(
            id: PaymentId::fromString($model->id),
            amount: Money::ofRub($model->amount),
            status: $model->status,
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
            capturedAmountKopecks: (int) ($model->captured_amount ?? 0),
            threeDsRequired: (bool) ($model->three_ds_required ?? false),
            threeDsChallengeUrl: $model->three_ds_challenge_url,
        );
    }
}
