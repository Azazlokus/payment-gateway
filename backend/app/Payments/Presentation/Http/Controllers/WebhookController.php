<?php

declare(strict_types=1);

namespace App\Payments\Presentation\Http\Controllers;

use App\Payments\Domain\Contracts\PaymentProviderInterface;
use App\Payments\Infrastructure\Jobs\ProcessYooKassaWebhookJob;
use App\Payments\Infrastructure\Observability\PaymentLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;

final class WebhookController extends Controller
{
    public function __construct(
        private readonly PaymentProviderInterface $provider,
        private readonly PaymentLogger $logger,
    ) {}

    #[OA\Post(
        path: '/webhook/yookassa',
        summary: 'Webhook от YooKassa',
        description: <<<'TEXT'
            Принимает JSON-уведомления от YooKassa об изменении статуса платежа или возврата.

            **Поддерживаемые события:**
            - `payment.succeeded` — платёж прошёл успешно
            - `payment.canceled` — платёж отменён
            - `refund.succeeded` — возврат подтверждён

            **Безопасность:**
            - IP-фильтрация по официальным CIDR YooKassa
            - Обработка асинхронна: вебхук ставит задачу в очередь Horizon (Redis) и возвращает `200` немедленно
            - Exponential backoff: 10s → 30s → 60s → 120s → 300s (5 попыток)
            - При исчерпании попыток — критический лог + алерт в Slack
            TEXT,
        tags: ['Webhook'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/YooKassaWebhookPayload')
        ),
        responses: [
            new OA\Response(response: 200, description: 'Webhook принят и поставлен в очередь', content: new OA\JsonContent(
                properties: [new OA\Property(property: 'message', type: 'string', example: 'ok')]
            )),
            new OA\Response(response: 403, description: 'Запрос с неразрешённого IP или без нужных полей'),
        ]
    )]
    public function yookassa(Request $request): JsonResponse
    {
        $payload = $request->all();

        if (! $this->provider->verifyWebhook($payload, $request->headers->all())) {
            $this->logger->warning('Webhook rejected', [
                'ip' => $request->ip(),
                'event' => $payload['event'] ?? 'unknown',
            ]);

            return response()->json(['message' => 'Forbidden'], Response::HTTP_FORBIDDEN);
        }

        $event = $payload['event'] ?? '';

        $this->logger->info('Webhook received, dispatching job', ['event' => $event]);

        ProcessYooKassaWebhookJob::dispatch($payload);

        return response()->json(['message' => 'ok'], Response::HTTP_OK);
    }
}
