<?php

declare(strict_types=1);

namespace App\Payments\Infrastructure\Observability;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class AuditLogger
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function log(
        string $action,
        string|null $subjectType = null,
        string|null $subjectId = null,
        array $metadata = [],
        Request|null $request = null,
    ): void {
        try {
            $ip = $request?->ip();
            $apiKeyHint = null;

            if ($request !== null) {
                $raw = (string) $request->header('X-Api-Key', '');
                if ($raw !== '') {
                    $apiKeyHint = '...' . substr($raw, -4);
                }
            }

            DB::table('audit_logs')->insert([
                'action'       => $action,
                'subject_type' => $subjectType,
                'subject_id'   => $subjectId,
                'ip'           => $ip,
                'api_key_hint' => $apiKeyHint,
                'metadata'     => $metadata !== [] ? json_encode($metadata, JSON_THROW_ON_ERROR) : null,
                'created_at'   => now(),
            ]);
        } catch (\Throwable) {
            // Аудит не должен ронять запрос
        }
    }
}
