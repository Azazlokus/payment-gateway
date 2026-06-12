<?php

declare(strict_types=1);

namespace App\Contexts\Payments\Infrastructure\Persistence\Models;

use App\Contexts\Payments\Domain\Enums\PaymentMethodType;
use App\Contexts\Payments\Infrastructure\Tenant\TenantContext;
use App\Contexts\Payments\Infrastructure\Tenant\TenantScope;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string|null $tenant_id
 * @property string $customer_id
 * @property string $provider
 * @property PaymentMethodType $type
 * @property string $token
 * @property string $last4
 * @property string $brand
 * @property string|null $expires_at
 * @property string|null $fingerprint
 * @property bool $is_active
 * @property array<string, mixed>|null $metadata
 */
final class PaymentMethodModel extends Model
{
    use HasUlids;

    protected $table = 'payment_methods';

    protected $fillable = [
        'id',
        'tenant_id',
        'customer_id',
        'provider',
        'type',
        'token',
        'last4',
        'brand',
        'expires_at',
        'fingerprint',
        'is_active',
        'metadata',
    ];

    protected $casts = [
        'type' => PaymentMethodType::class,
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];

    protected $hidden = [
        'token',
    ];

    protected static function booted(): void
    {
        if (config('payments.multi_tenant.enabled', false)) {
            self::addGlobalScope(new TenantScope(app(TenantContext::class)));
        }
    }
}
