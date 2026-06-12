<?php

declare(strict_types=1);

namespace App\Contexts\Payments\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

final class OutboundWebhookLog extends Model
{
    use HasUlids;

    protected $table = 'outbound_webhook_logs';

    protected $fillable = [
        'payment_id',
        'url',
        'payload',
        'attempt',
        'response_status',
        'response_body',
        'duration_ms',
        'success',
        'error',
        'sent_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'success' => 'boolean',
        'sent_at' => 'datetime',
    ];
}
