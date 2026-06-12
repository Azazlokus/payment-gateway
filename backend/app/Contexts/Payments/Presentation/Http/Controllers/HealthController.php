<?php

declare(strict_types=1);

namespace App\Contexts\Payments\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;

final class HealthController extends Controller
{
    #[OA\Get(
        path: '/health',
        summary: 'Health check',
        description: 'Проверяет доступность сервиса и базы данных',
        tags: ['System'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Сервис работает',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'string', example: 'ok'),
                        new OA\Property(property: 'db', type: 'string', example: 'ok'),
                        new OA\Property(property: 'redis', type: 'string', example: 'ok'),
                        new OA\Property(property: 'horizon', type: 'string', example: 'running'),
                    ]
                )
            ),
            new OA\Response(
                response: 503,
                description: 'Сервис недоступен',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'string', example: 'error'),
                        new OA\Property(property: 'db', type: 'string', example: 'unavailable'),
                        new OA\Property(property: 'redis', type: 'string', example: 'unavailable'),
                        new OA\Property(property: 'horizon', type: 'string', example: 'inactive'),
                    ]
                )
            ),
        ]
    )]
    public function check(): JsonResponse
    {
        try {
            DB::select('select 1');
            $dbStatus = 'ok';
        } catch (\Throwable) {
            $dbStatus = 'unavailable';
        }

        try {
            Redis::ping();
            $redisStatus = 'ok';
        } catch (\Throwable) {
            $redisStatus = 'unavailable';
        }

        // Horizon записывает свой статус в Redis под ключом horizon:master
        // Возможные значения: running, paused, или ключ отсутствует (inactive)
        try {
            $horizonStatus = Cache::store('redis')->get('horizon:status') ?? 'inactive';
        } catch (\Throwable) {
            $horizonStatus = 'inactive';
        }

        // БД — обязательна, Redis — желателен (degraded, но не 503)
        $healthy = $dbStatus === 'ok';

        return response()->json(
            [
                'status' => $healthy ? 'ok' : 'error',
                'db' => $dbStatus,
                'redis' => $redisStatus,
                'horizon' => $horizonStatus,
            ],
            $healthy ? Response::HTTP_OK : Response::HTTP_SERVICE_UNAVAILABLE,
        );
    }
}
