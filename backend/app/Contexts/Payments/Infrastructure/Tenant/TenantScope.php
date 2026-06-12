<?php

declare(strict_types=1);

namespace App\Contexts\Payments\Infrastructure\Tenant;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/** @implements Scope<Model> */
final readonly class TenantScope implements Scope
{
    public function __construct(private TenantContext $context) {}

    /** @param Builder<covariant Model> $builder */
    public function apply(Builder $builder, Model $model): void
    {
        if ($this->context->has()) {
            $builder->where($model->getTable().'.tenant_id', $this->context->get()->toString());
        }
    }
}
