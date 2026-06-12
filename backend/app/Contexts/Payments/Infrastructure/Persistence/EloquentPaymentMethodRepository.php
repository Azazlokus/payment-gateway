<?php

declare(strict_types=1);

namespace App\Contexts\Payments\Infrastructure\Persistence;

use App\Contexts\Payments\Domain\Aggregates\PaymentMethod;
use App\Contexts\Payments\Domain\Contracts\PaymentMethodRepositoryInterface;
use App\Contexts\Payments\Domain\ValueObjects\CardFingerprint;
use App\Contexts\Payments\Domain\ValueObjects\PaymentMethodId;
use App\Contexts\Payments\Domain\ValueObjects\TenantId;
use App\Contexts\Payments\Infrastructure\Persistence\Models\PaymentMethodEventModel;
use App\Contexts\Payments\Infrastructure\Persistence\Models\PaymentMethodModel;

final class EloquentPaymentMethodRepository implements PaymentMethodRepositoryInterface
{
    public function save(PaymentMethod $method): void
    {
        $attributes = [
            'customer_id' => $method->customerId(),
            'provider' => $method->provider(),
            'type' => $method->type()->value,
            'token' => $method->token(),
            'last4' => $method->last4(),
            'brand' => $method->brand(),
            'expires_at' => $method->expiresAt(),
            'fingerprint' => $method->fingerprint()?->toString(),
            'is_active' => $method->isActive(),
            'metadata' => $method->metadata(),
        ];

        if ($method->tenantId() instanceof TenantId) {
            $attributes['tenant_id'] = $method->tenantId()->toString();
        }

        PaymentMethodModel::updateOrCreate(
            ['id' => $method->id()->toString()],
            $attributes,
        );

        foreach ($method->pullDomainEvents() as $event) {
            PaymentMethodEventModel::create([
                'payment_method_id' => $method->id()->toString(),
                'event_id' => $event->eventId,
                'event_name' => $event->eventName(),
                'event_data' => $event->toArray(),
                'occurred_at' => $event->occurredAt,
            ]);
        }
    }

    public function findById(PaymentMethodId $id): ?PaymentMethod
    {
        $model = PaymentMethodModel::find($id->toString());

        return $model ? $this->hydrate($model) : null;
    }

    /** @return PaymentMethod[] */
    public function findByCustomerId(string $customerId): array
    {
        return PaymentMethodModel::where('customer_id', $customerId)
            ->where('is_active', true)
            ->get()
            ->map(fn (PaymentMethodModel $m): PaymentMethod => $this->hydrate($m))
            ->all();
    }

    public function findByFingerprint(string $customerId, string $fingerprint): ?PaymentMethod
    {
        // Не фильтруем по is_active: индекс (customer_id, fingerprint) уникален,
        // поэтому повторная токенизация удалённой карты должна найти и переиспользовать
        // существующую запись, а не создавать дубль (нарушение unique-индекса).
        $model = PaymentMethodModel::where('customer_id', $customerId)
            ->where('fingerprint', $fingerprint)
            ->first();

        return $model ? $this->hydrate($model) : null;
    }

    public function delete(PaymentMethodId $id): void
    {
        PaymentMethodModel::where('id', $id->toString())->delete();
    }

    private function hydrate(PaymentMethodModel $model): PaymentMethod
    {
        return PaymentMethod::restore(
            id: PaymentMethodId::fromString($model->id),
            tenantId: $model->tenant_id ? TenantId::fromString($model->tenant_id) : null,
            customerId: $model->customer_id,
            provider: $model->provider,
            type: $model->type,
            token: $model->token,
            last4: $model->last4,
            brand: $model->brand,
            expiresAt: $model->expires_at,
            fingerprint: $model->fingerprint ? CardFingerprint::fromString($model->fingerprint) : null,
            isActive: $model->is_active,
            metadata: $model->metadata ?? [],
        );
    }
}
