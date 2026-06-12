<?php

declare(strict_types=1);

namespace App\Contexts\Payments\Infrastructure\Tenant;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

final class TenantModel extends Model
{
    use HasUlids;

    protected $table = 'tenants';

    protected $fillable = [
        'id',
        'name',
        'api_key',
        'webhook_url',
        'is_active',
        'settings',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'settings' => 'array',
    ];
}
