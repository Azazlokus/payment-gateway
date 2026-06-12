<?php

declare(strict_types=1);

namespace App\Contexts\Payments\Presentation\Http\Controllers;

use App\Contexts\Payments\Infrastructure\Observability\MetricsService;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use OpenApi\Attributes as OA;

final class MetricsController extends Controller
{
    public function __construct(
        private readonly MetricsService $metrics,
    ) {}

    #[OA\Get(
        path: '/metrics',
        summary: 'Prometheus-метрики',
        description: 'Возвращает метрики в формате Prometheus text exposition для scrape-а.',
        tags: ['Observability'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Метрики в Prometheus text format',
                content: new OA\MediaType(mediaType: 'text/plain'),
            ),
        ]
    )]
    public function __invoke(): Response
    {
        return response($this->metrics->dump(), 200, [
            'Content-Type' => 'text/plain; version=0.0.4; charset=utf-8',
        ]);
    }
}
