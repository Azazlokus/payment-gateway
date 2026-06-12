<?php

declare(strict_types=1);

namespace App\Contexts\Payments\Presentation\Http\Controllers;

use App\Contexts\Payments\Infrastructure\Jobs\ProcessRobokassaWebhookJob;
use App\Contexts\Payments\Infrastructure\Observability\PaymentLogger;
use App\Contexts\Payments\Infrastructure\Providers\RobokassaProvider;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;

final class RobokassaWebhookController extends Controller
{
    public function __construct(
        private readonly RobokassaProvider $provider,
        private readonly PaymentLogger $logger,
    ) {}

    #[OA\Post(
        path: '/webhook/robokassa',
        summary: 'ResultURL-вебхук от Robokassa',
        description: <<<'TEXT'
            Принимает уведомления от Robokassa об успешной оплате (ResultURL).

            **Особенности:**
            - Тело запроса — `application/x-www-form-urlencoded` (form POST), не JSON
            - Verifies MD5 signature: `strtoupper(md5("{OutSum}:{InvId}:{Password2}:Shp_paymentId={value}"))`
            - IP-фильтрация по CIDR: `185.26.103.0/24`, `185.60.211.0/24`
            - При успехе **обязательно** возвращать plain-text `"OK{InvId}"`, иначе Robokassa будет повторять запрос
            TEXT,
        tags: ['Webhook'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'application/x-www-form-urlencoded',
                schema: new OA\Schema(ref: '#/components/schemas/RobokassaWebhookPayload'),
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Webhook принят. Возвращает plain-text "OK{InvId}"',
                content: new OA\MediaType(
                    mediaType: 'text/plain',
                    schema: new OA\Schema(type: 'string', example: 'OK12345'),
                )
            ),
            new OA\Response(
                response: 403,
                description: 'Невалидная подпись или запрещённый IP',
                content: new OA\MediaType(
                    mediaType: 'text/plain',
                    schema: new OA\Schema(type: 'string', example: 'Invalid signature'),
                )
            ),
        ]
    )]
    public function handle(Request $request): Response
    {
        $payload = $request->all();
        $invId = (string) ($payload['InvId'] ?? '');

        if (! $this->provider->verifyWebhook($payload, [])) {
            $this->logger->warning('Robokassa webhook rejected', [
                'ip' => $request->ip(),
                'invId' => $invId,
            ]);

            return response()->make('Invalid signature', Response::HTTP_FORBIDDEN);
        }

        $this->logger->info('Robokassa webhook received, dispatching job', [
            'inv_id' => $invId,
            'shp_payment_id' => $payload['Shp_paymentId'] ?? 'unknown',
        ]);

        ProcessRobokassaWebhookJob::dispatch($payload);

        // Robokassa requires exactly this plain-text format to stop retrying
        return response()->make("OK{$invId}", Response::HTTP_OK);
    }
}
