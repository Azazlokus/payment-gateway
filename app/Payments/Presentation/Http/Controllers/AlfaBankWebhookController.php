<?php

declare(strict_types=1);

namespace App\Payments\Presentation\Http\Controllers;

use App\Payments\Infrastructure\Jobs\ProcessAlfaBankWebhookJob;
use App\Payments\Infrastructure\Observability\PaymentLogger;
use App\Payments\Infrastructure\Providers\AlfaBankProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;

final class AlfaBankWebhookController extends Controller
{
    public function __construct(
        private readonly AlfaBankProvider $provider,
        private readonly PaymentLogger $logger,
    ) {}

    #[OA\Post(
        path: '/webhook/alfabank',
        summary: 'Webhook от Альфа-Банка',
        description: <<<'TEXT'
            Принимает form POST уведомления от Альфа-Банка об изменении статуса заказа.

            **Верификация:** Альфа-Банк не присылает криптографическую подпись.
            Проверяется наличие обязательных полей `mdOrder` и `operation`.

            **Поддерживаемые операции:**
            - `deposited` — платёж успешно списан
            - `refunded` — возврат выполнен
            - `reversed` — платёж отменён
            - `declinedByTimeout` — истёк таймаут
            TEXT,
        tags: ['Webhook'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'application/x-www-form-urlencoded',
                schema: new OA\Schema(ref: '#/components/schemas/AlfaBankWebhookPayload'),
            ),
        ),
        responses: [
            new OA\Response(response: 200, description: 'Webhook принят', content: new OA\JsonContent(
                properties: [new OA\Property(property: 'message', type: 'string', example: 'ok')]
            )),
            new OA\Response(response: 403, description: 'Отсутствуют обязательные поля'),
        ]
    )]
    public function handle(Request $request): JsonResponse
    {
        $payload = $request->all();

        if (! $this->provider->verifyWebhook($payload, $request->headers->all())) {
            $this->logger->warning('Альфа-Банк: webhook отклонён', [
                'ip'        => $request->ip(),
                'md_order'  => $payload['mdOrder'] ?? 'unknown',
                'operation' => $payload['operation'] ?? 'unknown',
            ]);

            return response()->json(['message' => 'Forbidden'], Response::HTTP_FORBIDDEN);
        }

        $this->logger->info('Альфа-Банк: webhook получен, постановка в очередь', [
            'md_order'  => $payload['mdOrder'] ?? 'unknown',
            'operation' => $payload['operation'] ?? 'unknown',
        ]);

        ProcessAlfaBankWebhookJob::dispatch($payload);

        return response()->json(['message' => 'ok'], Response::HTTP_OK);
    }
}
