<?php

declare(strict_types=1);

namespace App\Contexts\Payments\Infrastructure\Persistence\Models;

use App\Contexts\Payments\Domain\Enums\PaymentStatus;
use App\Contexts\Payments\Infrastructure\Tenant\TenantContext;
use App\Contexts\Payments\Infrastructure\Tenant\TenantScope;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string|null $tenant_id
 * @property string $idempotency_key
 * @property string|null $external_id
 * @property string|null $payment_method_id
 * @property string $provider
 * @property int $amount
 * @property int|null $refunded_amount
 * @property int|null $captured_amount
 * @property string $currency
 * @property string $description
 * @property PaymentStatus $status
 * @property string|null $confirmation_url
 * @property bool|null $three_ds_required
 * @property string|null $three_ds_challenge_url
 * @property array<string, mixed>|null $metadata
 */
final class PaymentModel extends Model
{
    use HasUlids;
    use SoftDeletes;

    protected $table = 'payments';

    /** @var list<string> */
    protected $fillable = [
        'id',
        'tenant_id',
        'idempotency_key',
        'external_id',
        'payment_method_id',
        'provider',
        'amount',
        'refunded_amount',
        'captured_amount',
        'currency',
        'description',
        'status',
        'confirmation_url',
        'three_ds_required',
        'three_ds_challenge_url',
        'metadata',
    ];

    protected static function booted(): void
    {
        if (config('payments.multi_tenant.enabled', false)) {
            self::addGlobalScope(new TenantScope(app(TenantContext::class)));
        }
    }

    protected $casts = [
        'status' => PaymentStatus::class,
        'metadata' => 'array',
        'three_ds_required' => 'boolean',
    ];

    /** @return HasMany<PaymentEventModel, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(PaymentEventModel::class, 'payment_id');
    }

    /** @return HasMany<PaymentSplitModel, $this> */
    public function splits(): HasMany
    {
        return $this->hasMany(PaymentSplitModel::class, 'payment_id');
    }
}
