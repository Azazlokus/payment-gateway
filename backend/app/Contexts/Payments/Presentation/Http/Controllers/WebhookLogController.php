<?php

declare(strict_types=1);

namespace App\Contexts\Payments\Presentation\Http\Controllers;

use App\Contexts\Payments\Infrastructure\Persistence\Models\OutboundWebhookLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

final class WebhookLogController extends Controller
{
    /**
     * GET /api/v1/payments/{id}/webhook-logs
     * История исходящих уведомлений по платежу.
     */
    public function forPayment(string $id): JsonResponse
    {
        $logs = OutboundWebhookLog::where('payment_id', $id)
            ->latest('sent_at')
            ->limit(50)
            ->get()
            ->map(fn ($log) => [
                'id' => $log->id,
                'url' => $log->url,
                'attempt' => $log->attempt,
                'success' => $log->success,
                'response_status' => $log->response_status,
                'response_body' => $log->response_body,
                'duration_ms' => $log->duration_ms,
                'error' => $log->error,
                'sent_at' => $log->sent_at->toIso8601String(),
            ]);

        return response()->json(['data' => $logs]);
    }

    /**
     * GET /api/v1/webhook-logs
     * Общий список всех исходящих уведомлений с фильтрацией.
     */
    public function index(Request $request): JsonResponse
    {
        $query = OutboundWebhookLog::latest('sent_at');

        if ($request->boolean('failed')) {
            $query->where('success', false);
        }

        if ($request->filled('payment_id')) {
            $query->where('payment_id', $request->query('payment_id'));
        }

        $logs = $query->paginate(30);

        return response()->json([
            'data' => $logs->map(fn ($log) => [
                'id' => $log->id,
                'payment_id' => $log->payment_id,
                'url' => $log->url,
                'success' => $log->success,
                'response_status' => $log->response_status,
                'duration_ms' => $log->duration_ms,
                'error' => $log->error,
                'sent_at' => $log->sent_at->toIso8601String(),
            ])->values(),
            'total' => $logs->total(),
            'current_page' => $logs->currentPage(),
            'last_page' => $logs->lastPage(),
        ]);
    }
}
