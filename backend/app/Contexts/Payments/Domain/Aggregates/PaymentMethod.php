<?php

declare(strict_types=1);

namespace App\Contexts\Payments\Domain\Aggregates;

use App\Contexts\Payments\Domain\Enums\PaymentMethodType;
use App\Contexts\Payments\Domain\Events\DomainEvent;
use App\Contexts\Payments\Domain\Events\PaymentMethodWasDeleted;
use App\Contexts\Payments\Domain\Events\PaymentMethodWasTokenized;
use App\Contexts\Payments\Domain\ValueObjects\CardFingerprint;
use App\Contexts\Payments\Domain\ValueObjects\PaymentMethodId;
use App\Contexts\Payments\Domain\ValueObjects\TenantId;

final class PaymentMethod
{
    /** @var DomainEvent[] */
    private array $domainEvents = [];

    private function __construct(
        private readonly PaymentMethodId $id,
        private readonly ?TenantId $tenantId,
        private readonly string $customerId,
        private readonly string $provider,
        private readonly PaymentMethodType $type,
        private string $token,
        private string $last4,
        private string $brand,
        private ?string $expiresAt,
        private readonly ?CardFingerprint $fingerprint,
        private bool $isActive,
        /** @var array<string, mixed> */
        private readonly array $metadata = [],
    ) {}

    /**
     * @param  array<string, mixed>  $metadata
     */
    public static function create(
        PaymentMethodId $id,
        ?TenantId $tenantId,
        string $customerId,
        string $provider,
        PaymentMethodType $type,
        string $token,
        string $last4,
        string $brand,
        ?string $expiresAt = null,
        ?CardFingerprint $fingerprint = null,
        array $metadata = [],
    ): self {
        $method = new self(
            id: $id,
            tenantId: $tenantId,
            customerId: $customerId,
            provider: $provider,
            type: $type,
            token: $token,
            last4: $last4,
            brand: $brand,
            expiresAt: $expiresAt,
            fingerprint: $fingerprint,
            isActive: true,
            metadata: $metadata,
        );

        $method->recordEvent(new PaymentMethodWasTokenized(
            paymentMethodId: $id->toString(),
            customerId: $customerId,
            provider: $provider,
            type: $type->value,
            last4: $last4,
        ));

        return $method;
    }

    public function deactivate(): void
    {
        $this->isActive = false;

        $this->recordEvent(new PaymentMethodWasDeleted(
            paymentMethodId: $this->id->toString(),
            customerId: $this->customerId,
        ));
    }

    /**
     * Повторная токенизация ранее удалённой карты: переиспользуем существующую
     * запись (того же fingerprint), обновляя токен и реквизиты от провайдера.
     */
    public function reactivate(string $token, string $last4, string $brand, ?string $expiresAt): void
    {
        $this->token = $token;
        $this->last4 = $last4;
        $this->brand = $brand;
        $this->expiresAt = $expiresAt;
        $this->isActive = true;

        $this->recordEvent(new PaymentMethodWasTokenized(
            paymentMethodId: $this->id->toString(),
            customerId: $this->customerId,
            provider: $this->provider,
            type: $this->type->value,
            last4: $last4,
        ));
    }

    private function recordEvent(DomainEvent $event): void
    {
        $this->domainEvents[] = $event;
    }

    /** @return DomainEvent[] */
    public function pullDomainEvents(): array
    {
        $events = $this->domainEvents;
        $this->domainEvents = [];

        return $events;
    }

    public function id(): PaymentMethodId
    {
        return $this->id;
    }

    public function tenantId(): ?TenantId
    {
        return $this->tenantId;
    }

    public function customerId(): string
    {
        return $this->customerId;
    }

    public function provider(): string
    {
        return $this->provider;
    }

    public function type(): PaymentMethodType
    {
        return $this->type;
    }

    public function token(): string
    {
        return $this->token;
    }

    public function last4(): string
    {
        return $this->last4;
    }

    public function brand(): string
    {
        return $this->brand;
    }

    public function expiresAt(): ?string
    {
        return $this->expiresAt;
    }

    public function fingerprint(): ?CardFingerprint
    {
        return $this->fingerprint;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    /** @return array<string, mixed> */
    public function metadata(): array
    {
        return $this->metadata;
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public static function restore(
        PaymentMethodId $id,
        ?TenantId $tenantId,
        string $customerId,
        string $provider,
        PaymentMethodType $type,
        string $token,
        string $last4,
        string $brand,
        ?string $expiresAt,
        ?CardFingerprint $fingerprint,
        bool $isActive,
        array $metadata = [],
    ): self {
        return new self(
            id: $id,
            tenantId: $tenantId,
            customerId: $customerId,
            provider: $provider,
            type: $type,
            token: $token,
            last4: $last4,
            brand: $brand,
            expiresAt: $expiresAt,
            fingerprint: $fingerprint,
            isActive: $isActive,
            metadata: $metadata,
        );
    }
}
