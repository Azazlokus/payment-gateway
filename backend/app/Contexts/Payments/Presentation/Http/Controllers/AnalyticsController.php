<?php

declare(strict_types=1);

namespace App\Contexts\Payments\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

/**
 * GET /api/v1/analytics/revenue   — выручка по дням (последние N дней)
 * GET /api/v1/analytics/funnel    — воронка конверсии по статусам
 */
final class AnalyticsController extends Controller
{
    /**
     * Выручка по дням: сумма успешных платежей сгруппированная по дате.
     */
    public function revenue(Request $request): JsonResponse
    {
        $days = min((int) $request->query('days', 30), 365);
        $provider = $request->query('provider');

        $query = DB::table('payments')
            ->where('status', 'Succeeded')
            ->where('created_at', '>=', now()->subDays($days))
            ->whereNull('deleted_at')
            ->selectRaw('DATE(created_at) as date, SUM(amount) as revenue_kopecks, COUNT(*) as count');

        if ($provider) {
            $query->where('provider', $provider);
        }

        $rows = $query->groupBy('date')->orderBy('date')->get();

        // Заполняем пропущенные дни нулями
        $filled = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $filled[$date] = ['date' => $date, 'revenue_kopecks' => 0, 'revenue_rub' => 0, 'count' => 0];
        }

        foreach ($rows as $row) {
            if (isset($filled[$row->date])) {
                $filled[$row->date] = [
                    'date' => $row->date,
                    'revenue_kopecks' => (int) $row->revenue_kopecks,
                    'revenue_rub' => round((int) $row->revenue_kopecks / 100, 2),
                    'count' => (int) $row->count,
                ];
            }
        }

        return response()->json(['data' => array_values($filled), 'days' => $days]);
    }

    /**
     * Воронка конверсии: количество и доля платежей по каждому статусу.
     */
    public function funnel(Request $request): JsonResponse
    {
        $from = $request->query('from', now()->subDays(30)->toDateString());
        $to = $request->query('to', now()->toDateString());

        $rows = DB::table('payments')
            ->whereNull('deleted_at')
            ->whereBetween(DB::raw('DATE(created_at)'), [$from, $to])
            ->selectRaw('status, COUNT(*) as count, SUM(amount) as total_kopecks')
            ->groupBy('status')
            ->get();

        $total = $rows->sum('count');

        $funnel = $rows->map(fn ($row) => [
            'status' => $row->status,
            'count' => (int) $row->count,
            'total_rub' => round((int) $row->total_kopecks / 100, 2),
            'conversion_pct' => $total > 0 ? round((int) $row->count / $total * 100, 1) : 0,
        ])->values();

        return response()->json([
            'data' => $funnel,
            'total' => (int) $total,
            'from' => $from,
            'to' => $to,
        ]);
    }
}
