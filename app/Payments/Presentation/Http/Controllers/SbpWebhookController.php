<?php

declare(strict_types=1);

namespace App\Payments\Presentation\Http\Controllers;

use App\Payments\Infrastructure\Jobs\ProcessSbpWebhookJob;
use App\Payments\Infrastructure\Observability\PaymentLogger;
use App\Payments\Infrastructure\Providers\SbpProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;

final class SbpWebhookController extends Controller
{
    public function __construct(
        private readonly SbpProvider $provider,
        private readonly PaymentLogger $logger,
    ) {}

    #[OA\Post(
        path: '/webhook/sbp',
        summary: 'Webhook от СБП',
        description: <<<'TEXT'
            Принимает JSON-уведомления от банка-эквайера об изменении статуса QR-платежа.

            **Верификация:** заголовок `X-Api-Key` сверяется с `SBP_WEBHOOK_SECRET`.

            **Поддерживаемые статусы:**
            - `PAID` — платёж прошёл успешно
            - `CANCELLED` / `EXPIRED` — платёж отменён или истёк
            TEXT,
        tags: ['Webhook'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/SbpWebhookPayload'),
        ),
        responses: [
            new OA\Response(response: 200, description: 'Webhook принят', content: new OA\JsonContent(
                properties: [new OA\Property(property: 'message', type: 'string', example: 'ok')]
            )),
            new OA\Response(response: 403, description: 'Невалидный X-Api-Key или отсутствуют обязательные поля'),
        ]
    )]
    public function handle(Request $request): JsonResponse
    {
        $payload = $request->all();

        if (! $this->provider->verifyWebhook($payload, $request->headers->all())) {
            $this->logger->warning('СБП: webhook отклонён', [
                'ip'    => $request->ip(),
                'qr_id' => $payload['qrId'] ?? 'unknown',
            ]);

            return response()->json(['message' => 'Forbidden'], Response::HTTP_FORBIDDEN);
        }

        $this->logger->info('СБП: webhook получен, постановка в очередь', [
            'qr_id'  => $payload['qrId'] ?? 'unknown',
            'status' => $payload['status'] ?? 'unknown',
        ]);

        ProcessSbpWebhookJob::dispatch($payload);

        return response()->json(['message' => 'ok'], Response::HTTP_OK);
    }
}
