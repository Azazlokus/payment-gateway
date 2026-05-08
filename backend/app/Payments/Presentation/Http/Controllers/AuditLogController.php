<?php

declare(strict_types=1);

namespace App\Payments\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

/**
 * GET /api/v1/audit-logs
 * Просмотр лога аудита с фильтрацией.
 */
final class AuditLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = DB::table('audit_logs')->orderByDesc('created_at');

        if ($request->filled('action')) {
            $query->where('action', $request->query('action'));
        }

        if ($request->filled('subject_id')) {
            $query->where('subject_id', $request->query('subject_id'));
        }

        if ($request->filled('ip')) {
            $query->where('ip', $request->query('ip'));
        }

        if ($request->filled('from')) {
            $query->where('created_at', '>=', $request->query('from'));
        }

        if ($request->filled('to')) {
            $query->where('created_at', '<=', $request->query('to'));
        }

        $perPage = min((int) $request->query('per_page', 30), 100);
        $page    = max((int) $request->query('page', 1), 1);
        $offset  = ($page - 1) * $perPage;

        $total = (clone $query)->count();
        $rows  = $query->offset($offset)->limit($perPage)->get();

        return response()->json([
            'data' => $rows->map(fn ($row) => [
                'id'           => $row->id,
                'action'       => $row->action,
                'subject_type' => $row->subject_type,
                'subject_id'   => $row->subject_id,
                'ip'           => $row->ip,
                'api_key_hint' => $row->api_key_hint,
                'metadata'     => $row->metadata ? json_decode($row->metadata, true) : null,
                'created_at'   => $row->created_at,
            ])->values(),
            'total'        => $total,
            'current_page' => $page,
            'last_page'    => (int) ceil($total / $perPage),
        ]);
    }
}
