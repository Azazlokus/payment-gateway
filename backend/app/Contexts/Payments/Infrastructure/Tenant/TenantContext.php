<?php

declare(strict_types=1);

namespace App\Contexts\Payments\Infrastructure\Tenant;

use App\Contexts\Payments\Domain\ValueObjects\TenantId;
use RuntimeException;

final class TenantContext
{
    private ?TenantId $tenantId = null;

    public function set(TenantId $tenantId): void
    {
        $this->tenantId = $tenantId;
    }

    public function get(): TenantId
    {
        if (! $this->tenantId instanceof TenantId) {
            throw new RuntimeException('Tenant context has not been set');
        }

        return $this->tenantId;
    }

    public function has(): bool
    {
        return $this->tenantId instanceof TenantId;
    }

    public function clear(): void
    {
        $this->tenantId = null;
    }
}
