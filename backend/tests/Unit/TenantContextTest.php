<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contexts\Payments\Domain\ValueObjects\TenantId;
use App\Contexts\Payments\Infrastructure\Tenant\TenantContext;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class TenantContextTest extends TestCase
{
    public function test_set_and_get_tenant(): void
    {
        $context = new TenantContext;
        $tenantId = TenantId::generate();

        $context->set($tenantId);

        $this->assertTrue($context->has());
        $this->assertTrue($tenantId->equals($context->get()));
    }

    public function test_get_throws_when_not_set(): void
    {
        $context = new TenantContext;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Tenant context has not been set');

        $context->get();
    }

    public function test_has_returns_false_when_not_set(): void
    {
        $context = new TenantContext;

        $this->assertFalse($context->has());
    }

    public function test_clear_removes_tenant(): void
    {
        $context = new TenantContext;
        $context->set(TenantId::generate());

        $context->clear();

        $this->assertFalse($context->has());
    }
}
