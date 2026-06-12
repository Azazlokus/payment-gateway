<?php

declare(strict_types=1);

namespace App\Contexts\Payments\Presentation\Http\Controllers;

use App\Contexts\Payments\Infrastructure\Jobs\ProcessCloudPaymentsWebhookJob;
use App\Contexts\Payments\Infrastructure\Observability\PaymentLogger;
use App\Contexts\Payments\Infrastructure\Providers\CloudPaymentsProvider;
use App\Contexts\Payments\Infrastructure\Webhook\ReplayProtector;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;

final class CloudPaymentsWebhookController extends Controller
{
    public function __construct(
        private readonly CloudPaymentsProvider $provider,
        private readonly PaymentLogger $logger,
        private readonly ReplayProtector $replayProtector,
    ) {}

    #[OA\Post(path: '/webhook/cloudpayments', description: <<<'TEXT'
            Принимает JSON-уведомления от CloudPayments об изменении статуса транзакции.

            **Верификация:** заголовок `Content-HMAC` = Base64(HMAC-SHA256(body, apiSecret)).

            **Поддерживаемые статусы:**
            - `Completed` / `Authorized` — оплата успешна
            - `Cancelled` / `Declined` — отменена
            - `Refunded` — возврат
            TEXT, summary: 'Webhook от CloudPayments', requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'TransactionId', type: 'integer', example: 12345678),
                new OA\Property(property: 'InvoiceId', type: 'string', example: '01HV9Z7BKQE4GNKR2XQVP0M8T'),
                new OA\Property(property: 'Status', type: 'string', enum: ['Completed', 'Authorized', 'Cancelled', 'Declined', 'Refunded']),
                new OA\Property(property: 'Amount', type: 'number', format: 'float', example: 500.00),
            ]
        ),
    ), tags: ['Webhook'], parameters: [
        new OA\Parameter(
            name: 'Content-HMAC',
            description: 'HMAC-SHA256 подпись тела запроса',
            in: 'header',
            required: true,
            schema: new OA\Schema(type: 'string'),
        ),
    ], responses: [
        new OA\Response(response: 200, description: 'Webhook принят', content: new OA\JsonContent(
            properties: [new OA\Property(property: 'code', type: 'integer', example: 0)]
        )),
        new OA\Response(response: 403, description: 'Неверная подпись'),
    ])]
    public function handle(Request $request): JsonResponse
    {
        $nonce = (string) $request->header('X-Request-Id', uniqid('', true));
        $timestamp = $request->integer('DateTimeUTC') ?: time();

        $this->replayProtector->verify($nonce, $timestamp);

        $payload = $request->all();

        if (! $this->provider->verifyWebhook($payload, $request->headers->all())) {
            $this->logger->warning('CloudPayments: webhook отклонён', [
                'ip' => $request->ip(),
                'transaction_id' => $payload['TransactionId'] ?? 'unknown',
            ]);

            // CloudPayments ожидает code=13 для отклонения без повтора
            return response()->json(['code' => 13], Response::HTTP_FORBIDDEN);
        }

        $this->logger->info('CloudPayments: webhook получен, постановка в очередь', [
            'transaction_id' => $payload['TransactionId'] ?? 'unknown',
            'status' => $payload['Status'] ?? 'unknown',
        ]);

        ProcessCloudPaymentsWebhookJob::dispatch($payload);

        // CloudPayments ожидает code=0 для подтверждения
        return response()->json(['code' => 0], Response::HTTP_OK);
    }
}
