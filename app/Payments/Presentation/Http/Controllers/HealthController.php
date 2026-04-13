<?php

declare(strict_types=1);

namespace App\Payments\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
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

        $healthy = $dbStatus === 'ok';

        return response()->json(
            ['status' => $healthy ? 'ok' : 'error', 'db' => $dbStatus],
            $healthy ? Response::HTTP_OK : Response::HTTP_SERVICE_UNAVAILABLE,
        );
    }
}
