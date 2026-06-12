<?php

declare(strict_types=1);

namespace App\Contexts\Payments\Presentation\Http\Controllers;

use App\Contexts\Payments\Application\Bus\CommandBus;
use App\Contexts\Payments\Application\Commands\CancelPayment\CancelPaymentCommand;
use App\Contexts\Payments\Application\Commands\CapturePayment\CapturePaymentCommand;
use App\Contexts\Payments\Application\Commands\CreatePayment\CreatePaymentCommand;
use App\Contexts\Payments\Application\Commands\RefundPayment\RefundPaymentCommand;
use App\Contexts\Payments\Application\Commands\RetryPayment\RetryPaymentCommand;
use App\Contexts\Payments\Application\Commands\SyncPayment\SyncPaymentCommand;
use App\Contexts\Payments\Application\DTOs\CreatePaymentOptionsDTO;
use App\Contexts\Payments\Application\DTOs\PaymentResultDTO;
use App\Contexts\Payments\Application\DTOs\ReceiptDTO;
use App\Contexts\Payments\Application\DTOs\ReceiptItemDTO;
use App\Contexts\Payments\Application\DTOs\SplitRuleDTO;
use App\Contexts\Payments\Domain\Aggregates\Payment;
use App\Contexts\Payments\Domain\Contracts\PaymentRepositoryInterface;
use App\Contexts\Payments\Domain\ValueObjects\PaymentId;
use App\Contexts\Payments\Infrastructure\Observability\AuditLogger;
use App\Contexts\Payments\Infrastructure\Observability\NotificationService;
use App\Contexts\Payments\Presentation\Http\Requests\CreatePaymentRequest;
use App\Contexts\Payments\Presentation\Http\Requests\RefundPaymentRequest;
use App\Contexts\Payments\Presentation\Http\Resources\PaymentResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class PaymentController extends Controller
{
    public function __construct(
        private readonly CommandBus $bus,
        private readonly PaymentRepositoryInterface $repository,
        private readonly NotificationService $notifications,
        private readonly AuditLogger $auditLogger,
    ) {}

    #[OA\Get(path: '/payments', description: 'Возвращает постраничный список платежей с фильтрацией по статусу и дате', summary: 'Список платежей', tags: ['Payments'], parameters: [
        new OA\Parameter(name: 'status', description: 'Фильтр по статусу', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['Pending', 'Authorized', 'Succeeded', 'Cancelled', 'Refunded'])),
        new OA\Parameter(name: 'provider', description: 'Фильтр по провайдеру', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['yookassa', 'robokassa', 'cloudpayments', 'sbp', 'alfabank'])),
        new OA\Parameter(name: 'from_date', description: 'Дата от (Y-m-d)', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date', example: '2024-01-01')),
        new OA\Parameter(name: 'to_date', description: 'Дата до (Y-m-d)', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date', example: '2024-12-31')),
        new OA\Parameter(name: 'per_page', description: 'Размер страницы (1-100)', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 15, maximum: 100, minimum: 1)),
        new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 1, minimum: 1)),
    ], responses: [
        new OA\Response(
            response: 200,
            description: 'Список платежей',
            content: new OA\JsonContent(ref: '#/components/schemas/PaginatedPayments')
        ),
    ])]
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->query('per_page', 15), 100);
        $cursor = $request->string('cursor')->toString();
        $filters = array_filter([
            'status' => $request->query('status'),
            'provider' => $request->query('provider'),
            'from_date' => $request->query('from_date'),
            'to_date' => $request->query('to_date'),
        ]);

        $result = $this->repository->cursorPaginate($perPage, $cursor ?: null, $filters);

        return response()->json([
            'data' => array_map(
                fn (Payment $payment) => new PaymentResource(PaymentResultDTO::fromAggregate($payment))->resolve(),
                $result['data']
            ),
            'per_page' => $result['per_page'],
            'next_cursor' => $result['next_cursor'],
            'prev_cursor' => $result['prev_cursor'],
        ], Response::HTTP_OK);
    }

    #[OA\Post(path: '/payments', description: 'Создаёт платёж через YooKassa и возвращает ссылку для перехода на страницу оплаты', summary: 'Создать платёж', requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['amount', 'description', 'return_url'],
            properties: [
                new OA\Property(property: 'amount', description: 'Сумма в копейках (минимум 100 = 1 руб)', type: 'integer', example: 50000, minimum: 100),
                new OA\Property(property: 'description', description: 'Описание платежа', type: 'string', example: 'Оплата заказа №1234', maxLength: 255),
                new OA\Property(property: 'return_url', type: 'string', format: 'uri', example: 'https://example.com/payment/success'),
                new OA\Property(property: 'metadata', type: 'object', nullable: true),
                new OA\Property(property: 'payment_method_type', type: 'string', nullable: true, enum: ['bank_card', 'yoo_money', 'sbp', 'sberbank', 'tinkoff_bank', 'cash']),
                new OA\Property(property: 'confirmation_type', type: 'string', nullable: true, enum: ['redirect', 'embedded', 'qr', 'mobile_application']),
                new OA\Property(property: 'save_payment_method', description: 'Сохранить метод для рекуррентных платежей', type: 'boolean', nullable: true),
                new OA\Property(property: 'payment_method_id', description: 'ID сохранённого метода для рекуррентного списания', type: 'string', nullable: true),
                new OA\Property(
                    property: 'splits',
                    description: 'Правила разделения платежа между получателями (маркетплейс). Сумма splits ≤ amount.',
                    type: 'array',
                    items: new OA\Items(
                        properties: [
                            new OA\Property(property: 'account_id', description: 'ID аккаунта получателя в платёжной системе', type: 'string', example: '100500'),
                            new OA\Property(property: 'amount', description: 'Сумма в копейках', type: 'integer', example: 30000, minimum: 100),
                            new OA\Property(property: 'description', description: 'Описание перевода', type: 'string', example: 'Доля продавца'),
                        ]
                    ),
                    nullable: true,
                ),
            ]
        )
    ), tags: ['Payments'], parameters: [
        new OA\Parameter(
            name: 'Idempotency-Key',
            description: 'Ключ идемпотентности (UUID). Если не передан — генерируется автоматически',
            in: 'header',
            required: false,
            schema: new OA\Schema(type: 'string', format: 'uuid', example: '550e8400-e29b-41d4-a716-446655440000')
        ),
    ], responses: [
        new OA\Response(
            response: 201,
            description: 'Платёж создан',
            content: new OA\JsonContent(
                properties: [new OA\Property(property: 'data', ref: '#/components/schemas/PaymentResponse')]
            )
        ),
        new OA\Response(response: 422, description: 'Ошибка валидации', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
        new OA\Response(response: 429, description: 'Слишком много запросов'),
        new OA\Response(response: 500, description: 'Ошибка YooKassa', content: new OA\JsonContent(ref: '#/components/schemas/PaymentError')),
    ])]
    public function create(CreatePaymentRequest $request): JsonResponse
    {
        $metadata = $request->input('metadata', []);

        if ($request->filled('notification_url')) {
            $metadata['notification_url'] = $request->input('notification_url');
        }

        $result = $this->bus->dispatch(new CreatePaymentCommand(
            amountKopecks: $request->integer('amount'),
            description: $request->string('description')->toString(),
            returnUrl: $request->string('return_url')->toString(),
            idempotencyKey: $request->header('Idempotency-Key') ?? (string) Str::uuid(),
            userId: $request->user()?->id,
            metadata: $metadata,
            options: $this->buildOptions($request),
            provider: $request->input('provider'),
            manualCapture: (bool) $request->input('manual_capture', false),
            splits: array_map(
                fn (array $s): SplitRuleDTO => new SplitRuleDTO(
                    accountId: $s['account_id'],
                    amountKopecks: (int) $s['amount'],
                    description: $s['description'] ?? '',
                ),
                $request->input('splits', []),
            ),
        ));

        $this->auditLogger->log('payment.created', 'payment', $result->paymentId, ['amount' => $result->amount], $request);

        return response()->json(new PaymentResource($result), Response::HTTP_CREATED);
    }

    #[OA\Get(path: '/payments/{id}', description: 'Возвращает информацию о платеже по его внутреннему ID', summary: 'Получить платёж', tags: ['Payments'], parameters: [
        new OA\Parameter(name: 'id', description: 'ID платежа (ULID)', in: 'path', required: true, schema: new OA\Schema(type: 'string', example: '01HV9Z7BKQE4GNKR2XQVP0M8T')),
    ], responses: [
        new OA\Response(response: 200, description: 'Платёж найден', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/PaymentResponse')])),
        new OA\Response(response: 404, description: 'Платёж не найден', content: new OA\JsonContent(ref: '#/components/schemas/PaymentError')),
    ])]
    public function show(string $id): JsonResponse
    {
        try {
            $paymentId = PaymentId::fromString($id);
        } catch (\InvalidArgumentException) {
            return response()->json(['error' => 'not_found', 'message' => 'Payment not found'], Response::HTTP_NOT_FOUND);
        }

        $payment = $this->repository->findById($paymentId);

        if (! $payment instanceof Payment) {
            return response()->json(['error' => 'not_found', 'message' => 'Payment not found'], Response::HTTP_NOT_FOUND);
        }

        return response()->json(new PaymentResource(PaymentResultDTO::fromAggregate($payment)), Response::HTTP_OK);
    }

    #[OA\Post(path: '/payments/{id}/cancel', description: 'Отменяет платёж в статусе Pending. Платёж в терминальном статусе отменить нельзя.', summary: 'Отменить платёж', requestBody: new OA\RequestBody(
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'reason', description: 'Причина отмены', type: 'string', example: 'Отменено пользователем', maxLength: 255),
            ]
        )
    ), tags: ['Payments'], parameters: [
        new OA\Parameter(name: 'id', description: 'ID платежа (ULID)', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
    ], responses: [
        new OA\Response(response: 200, description: 'Платёж отменён', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/PaymentResponse')])),
        new OA\Response(response: 404, description: 'Платёж не найден', content: new OA\JsonContent(ref: '#/components/schemas/PaymentError')),
        new OA\Response(response: 409, description: 'Невозможно отменить (терминальный статус)', content: new OA\JsonContent(ref: '#/components/schemas/PaymentError')),
    ])]
    public function cancel(string $id): JsonResponse
    {
        $result = $this->bus->dispatch(new CancelPaymentCommand(
            paymentId: $id,
            reason: request()->input('reason', 'Отменено пользователем'),
        ));

        $this->auditLogger->log('payment.cancelled', 'payment', $id, [], request());

        return response()->json(new PaymentResource($result), Response::HTTP_OK);
    }

    #[OA\Post(path: '/payments/{id}/capture', description: 'Списывает средства с карты клиента. Работает только для платежей в статусе Authorized (двухстадийная оплата). Можно подтвердить на сумму ≤ авторизованной.', summary: 'Подтвердить (capture) авторизованный платёж', requestBody: new OA\RequestBody(
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'amount', description: 'Сумма capture в копейках. Если не указана — полная авторизованная сумма', type: 'integer', example: 10000, nullable: true, minimum: 100),
            ]
        )
    ), tags: ['Payments'], parameters: [
        new OA\Parameter(name: 'id', description: 'ID платежа (ULID)', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
    ], responses: [
        new OA\Response(response: 200, description: 'Платёж подтверждён', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/PaymentResponse')])),
        new OA\Response(response: 404, description: 'Платёж не найден', content: new OA\JsonContent(ref: '#/components/schemas/PaymentError')),
        new OA\Response(response: 409, description: 'Платёж не в статусе Authorized', content: new OA\JsonContent(ref: '#/components/schemas/PaymentError')),
        new OA\Response(response: 422, description: 'Провайдер не поддерживает capture', content: new OA\JsonContent(ref: '#/components/schemas/PaymentError')),
    ])]
    public function capture(string $id, Request $request): JsonResponse
    {
        $result = $this->bus->dispatch(new CapturePaymentCommand(
            paymentId: $id,
            amountKopecks: $request->integer('amount') ?: null,
        ));

        $this->auditLogger->log('payment.captured', 'payment', $id, [], $request);

        return response()->json(new PaymentResource($result), Response::HTTP_OK);
    }

    #[OA\Post(path: '/payments/{id}/refund', description: 'Создаёт возврат через YooKassa. Работает только для платежей в статусе Succeeded. Если сумма не указана — полный возврат.', summary: 'Вернуть платёж', requestBody: new OA\RequestBody(
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'amount', description: 'Сумма возврата в копейках. Если не указана — полный возврат', type: 'integer', example: 10000, nullable: true, minimum: 100),
                new OA\Property(property: 'reason', description: 'Причина возврата', type: 'string', example: 'Возврат по заявке клиента', maxLength: 255),
            ]
        )
    ), tags: ['Payments'], parameters: [
        new OA\Parameter(name: 'id', description: 'ID платежа (ULID)', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
    ], responses: [
        new OA\Response(response: 200, description: 'Возврат создан', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/PaymentResponse')])),
        new OA\Response(response: 404, description: 'Платёж не найден', content: new OA\JsonContent(ref: '#/components/schemas/PaymentError')),
        new OA\Response(response: 409, description: 'Ошибка состояния платежа', content: new OA\JsonContent(ref: '#/components/schemas/PaymentError')),
        new OA\Response(response: 500, description: 'Ошибка YooKassa', content: new OA\JsonContent(ref: '#/components/schemas/PaymentError')),
    ])]
    public function refund(string $id, RefundPaymentRequest $request): JsonResponse
    {
        $result = $this->bus->dispatch(new RefundPaymentCommand(
            paymentId: $id,
            amountKopecks: $request->integer('amount') ?: null,
            reason: $request->input('reason', ''),
            idempotencyKey: $request->header('Idempotency-Key'),
        ));

        $this->auditLogger->log('payment.refunded', 'payment', $id, [], $request);

        return response()->json(new PaymentResource($result), Response::HTTP_OK);
    }

    #[OA\Post(path: '/payments/{id}/sync', description: 'Запрашивает актуальный статус платежа у YooKassa и обновляет его в базе. Полезно для локальной разработки без публичного вебхука.', summary: 'Синхронизировать статус с YooKassa', tags: ['Payments'], parameters: [
        new OA\Parameter(name: 'id', description: 'ID платежа (ULID)', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
    ], responses: [
        new OA\Response(response: 200, description: 'Статус синхронизирован', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/PaymentResponse')])),
        new OA\Response(response: 404, description: 'Платёж не найден', content: new OA\JsonContent(ref: '#/components/schemas/PaymentError')),
    ])]
    public function sync(string $id): JsonResponse
    {
        $result = $this->bus->dispatch(new SyncPaymentCommand(paymentId: $id));

        return response()->json(new PaymentResource($result), Response::HTTP_OK);
    }

    #[OA\Get(path: '/payments/export', description: 'Стримит CSV-файл со всеми платежами, удовлетворяющими фильтрам. Поддерживает те же фильтры, что и GET /payments.', summary: 'Экспорт платежей в CSV', tags: ['Payments'], parameters: [
        new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
        new OA\Parameter(name: 'provider', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
        new OA\Parameter(name: 'from_date', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
        new OA\Parameter(name: 'to_date', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
    ], responses: [
        new OA\Response(response: 200, description: 'CSV-файл', content: new OA\MediaType(mediaType: 'text/csv')),
    ])]
    public function export(Request $request): StreamedResponse
    {
        $filters = array_filter([
            'status' => $request->query('status'),
            'provider' => $request->query('provider'),
            'from_date' => $request->query('from_date'),
            'to_date' => $request->query('to_date'),
        ]);

        $filename = 'payments-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($filters): void {
            $handle = fopen('php://output', 'w');

            if ($handle === false) {
                return;
            }

            fputcsv($handle, ['id', 'status', 'provider', 'amount', 'currency', 'description', 'external_id', 'created_at'], escape: '\\');

            foreach ($this->repository->cursor($filters) as $payment) {
                fputcsv($handle, [
                    $payment->id()->toString(),
                    $payment->status()->value,
                    $payment->provider(),
                    $payment->amount()->amount(),
                    $payment->amount()->currency()->value,
                    $payment->description(),
                    $payment->externalId()?->toString() ?? '',
                    '',
                ],
                    escape: '\\');
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    #[OA\Post(path: '/payments/{id}/retry', description: 'Создаёт новый платёж с теми же параметрами, что у отменённого. Работает только для платежей в статусе `Cancelled`.', summary: 'Повторить отменённый платёж', requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['return_url'],
            properties: [
                new OA\Property(property: 'return_url', type: 'string', format: 'uri', example: 'https://example.com/success'),
            ]
        )
    ), tags: ['Payments'], parameters: [
        new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
    ], responses: [
        new OA\Response(response: 201, description: 'Новый платёж создан', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/PaymentResponse')])),
        new OA\Response(response: 404, description: 'Платёж не найден'),
        new OA\Response(response: 409, description: 'Платёж не в статусе Cancelled'),
    ])]
    public function retry(string $id, Request $request): JsonResponse
    {
        $result = $this->bus->dispatch(new RetryPaymentCommand(
            paymentId: $id,
            returnUrl: (string) $request->input('return_url', ''),
            idempotencyKey: $request->header('Idempotency-Key') ?? (string) Str::uuid(),
        ));

        return response()->json(new PaymentResource($result), Response::HTTP_CREATED);
    }

    #[OA\Post(path: '/payments/{id}/resync', description: 'Повторно отправляет POST-уведомление на `notification_url` из метаданных платежа. Полезно когда эндпоинт клиента временно не отвечал.', summary: 'Повторно отправить исходящее уведомление', tags: ['Payments'], parameters: [
        new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
    ], responses: [
        new OA\Response(response: 200, description: 'Уведомление отправлено (или notification_url не задан)'),
        new OA\Response(response: 404, description: 'Платёж не найден'),
    ])]
    public function resync(string $id): JsonResponse
    {
        try {
            $paymentId = PaymentId::fromString($id);
        } catch (\InvalidArgumentException) {
            return response()->json(['error' => 'not_found', 'message' => 'Payment not found'], Response::HTTP_NOT_FOUND);
        }

        $payment = $this->repository->findById($paymentId);

        if (! $payment instanceof Payment) {
            return response()->json(['error' => 'not_found', 'message' => 'Payment not found'], Response::HTTP_NOT_FOUND);
        }

        $this->notifications->notify(PaymentResultDTO::fromAggregate($payment), $payment->metadata());

        return response()->json(['message' => 'Notification dispatched'], Response::HTTP_OK);
    }

    // ─── Private helpers ────────────────────────────────────────────────────

    private function buildOptions(CreatePaymentRequest $request): CreatePaymentOptionsDTO
    {
        $receipt = null;
        if ($request->has('receipt')) {
            $receiptData = $request->input('receipt');
            $items = array_map(fn (array $item): ReceiptItemDTO => new ReceiptItemDTO(
                description: $item['description'],
                quantity: (float) $item['quantity'],
                amountKopecks: (int) $item['amount'],
                vatCode: (int) $item['vat_code'],
                paymentSubject: $item['payment_subject'] ?? 'commodity',
                paymentMode: $item['payment_mode'] ?? 'full_payment',
            ), $receiptData['items'] ?? []);

            $receipt = new ReceiptDTO(
                items: $items,
                email: $receiptData['customer']['email'] ?? null,
                phone: $receiptData['customer']['phone'] ?? null,
            );
        }

        return new CreatePaymentOptionsDTO(
            receipt: $receipt,
            confirmationType: $request->input('confirmation_type', 'redirect'),
            paymentMethodType: $request->input('payment_method_type'),
            savePaymentMethod: (bool) $request->input('save_payment_method', false),
            paymentMethodId: $request->input('payment_method_id'),
        );
    }
}
