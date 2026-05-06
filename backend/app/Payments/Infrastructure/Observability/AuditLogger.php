<?php

declare(strict_types=1);

namespace App\Payments\Infrastructure\Observability;

use Illuminate\Support\Facades\DB;

final class AuditLogger
{
    /**
     * @param  array<string, mixed>  $properties
     */
    public function log(string $event, string $description, array $properties = []): void
    {
        DB::table('activity_log')->insert([
            'log_name' => 'payments',
            'description' => $description,
            'event' => $event,
            'properties' => json_encode($properties),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
