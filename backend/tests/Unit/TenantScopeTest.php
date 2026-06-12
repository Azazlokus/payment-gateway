<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contexts\Payments\Domain\ValueObjects\TenantId;
use App\Contexts\Payments\Infrastructure\Tenant\TenantContext;
use App\Contexts\Payments\Infrastructure\Tenant\TenantScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Mockery;
use PHPUnit\Framework\TestCase;

final class TenantScopeTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
    }

    public function test_applies_where_clause_when_tenant_is_set(): void
    {
        $context = new TenantContext;
        $tenantId = TenantId::generate();
        $context->set($tenantId);

        $scope = new TenantScope($context);

        $model = Mockery::mock(Model::class);
        $model->shouldReceive('getTable')->andReturn('payments');

        $applied = false;
        $builder = Mockery::mock(Builder::class);
        $builder->shouldReceive('where')
            ->once()
            ->with('payments.tenant_id', $tenantId->toString())
            ->andReturnUsing(function () use (&$applied, $builder) {
                $applied = true;

                return $builder;
            });

        $scope->apply($builder, $model);

        $this->assertTrue($applied);
    }

    public function test_does_not_apply_when_tenant_not_set(): void
    {
        $context = new TenantContext;
        $scope = new TenantScope($context);

        $model = Mockery::mock(Model::class);

        $applied = false;
        $builder = Mockery::mock(Builder::class);
        $builder->shouldReceive('where')->andReturnUsing(function () use (&$applied) {
            $applied = true;
        });

        $scope->apply($builder, $model);

        $this->assertFalse($applied);
    }
}
