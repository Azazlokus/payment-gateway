<?php

declare(strict_types=1);

namespace App\Contexts\Payments\Presentation\Http\Middleware;

use App\Contexts\Payments\Domain\ValueObjects\TenantId;
use App\Contexts\Payments\Infrastructure\Tenant\TenantContext;
use App\Contexts\Payments\Infrastructure\Tenant\TenantModel;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ResolveTenant
{
    public function __construct(private readonly TenantContext $context) {}

    public function handle(Request $request, Closure $next): mixed
    {
        if (! config('payments.multi_tenant.enabled', false)) {
            return $next($request);
        }

        $tenantId = $request->header('X-Tenant-Id');

        if ($tenantId === null || $tenantId === '') {
            return response()->json([
                'code' => 'tenant_required',
                'message' => 'X-Tenant-Id header is required',
            ], Response::HTTP_BAD_REQUEST);
        }

        $tenant = TenantModel::where('id', $tenantId)->where('is_active', true)->first();

        if ($tenant === null) {
            return response()->json([
                'code' => 'tenant_not_found',
                'message' => 'Tenant not found or inactive',
            ], Response::HTTP_FORBIDDEN);
        }

        $this->context->set(TenantId::fromString($tenant->id));

        return $next($request);
    }

    public function terminate(): void
    {
        $this->context->clear();
    }
}
