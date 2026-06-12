<?php

declare(strict_types=1);

namespace App\Contexts\Payments\Domain\Contracts;

use App\Contexts\Payments\Domain\ValueObjects\TenantId;

interface TenantAware
{
    public function tenantId(): TenantId;
}
