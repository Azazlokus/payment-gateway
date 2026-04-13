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
        description: 'Принимает уведомления от YooKassa об изменении статуса платежа. Доступен только с IP-адресов YooKassa.',
        tags: ['Webhook'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'event', type: 'string', example: 'payment.succeeded', enum: ['payment.succeeded', 'payment.canceled', 'refund.succeeded']),
                    new OA\Property(
                        property: 'object',
                        type: 'object',
                        properties: [
                            new OA\Property(property: 'id', type: 'string', example: '22d65900-000f-5000-a000-10d000000000'),
                            new OA\Property(property: 'status', type: 'string', example: 'succeeded'),
                        ]
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Webhook обработан'),
            new OA\Response(response: 403, description: 'Запрос с неразрешённого IP'),
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
