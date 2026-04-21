<?php

declare(strict_types=1);

namespace App\Payments\Presentation\Http\Controllers;

use App\Payments\Application\Bus\CommandBus;
use App\Payments\Application\Commands\CancelPayment\CancelPaymentCommand;
use App\Payments\Application\Commands\CreatePayment\CreatePaymentCommand;
use App\Payments\Application\Commands\RefundPayment\RefundPaymentCommand;
use App\Payments\Application\Commands\RetryPayment\RetryPaymentCommand;
use App\Payments\Application\Commands\SyncPayment\SyncPaymentCommand;
use App\Payments\Application\DTOs\CreatePaymentOptionsDTO;
use App\Payments\Application\DTOs\PaymentResultDTO;
use App\Payments\Application\DTOs\ReceiptDTO;
use App\Payments\Application\DTOs\ReceiptItemDTO;
use App\Payments\Infrastructure\Observability\NotificationService;
use App\Payments\Domain\Contracts\PaymentRepositoryInterface;
use App\Payments\Domain\ValueObjects\PaymentId;
use App\Payments\Presentation\Http\Requests\CreatePaymentRequest;
use App\Payments\Presentation\Http\Requests\RefundPaymentRequest;
use App\Payments\Presentation\Http\Resources\PaymentResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;

final class PaymentController extends Controller
{
    public function __construct(
        private readonly CommandBus $bus,
        private readonly PaymentRepositoryInterface $repository,
        private readonly NotificationService $notifications,
    ) {}

    #[OA\Get(
        path: '/payments',
        summary: 'Список платежей',
        description: 'Возвращает постраничный список платежей с фильтрацией по статусу и дате',
        tags: ['Payments'],
        parameters: [
            new OA\Parameter(name: 'status', in: 'query', required: false, description: 'Фильтр по статусу', schema: new OA\Schema(type: 'string', enum: ['Pending', 'Succeeded', 'Cancelled', 'Refunded'])),
            new OA\Parameter(name: 'provider', in: 'query', required: false, description: 'Фильтр по провайдеру', schema: new OA\Schema(type: 'string', enum: ['yookassa', 'robokassa', 'cloudpayments', 'sbp', 'alfabank'])),
            new OA\Parameter(name: 'from_date', in: 'query', required: false, description: 'Дата от (Y-m-d)', schema: new OA\Schema(type: 'string', format: 'date', example: '2024-01-01')),
            new OA\Parameter(name: 'to_date', in: 'query', required: false, description: 'Дата до (Y-m-d)', schema: new OA\Schema(type: 'string', format: 'date', example: '2024-12-31')),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, description: 'Размер страницы (1-100)', schema: new OA\Schema(type: 'integer', default: 15, minimum: 1, maximum: 100)),
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 1, minimum: 1)),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Список платежей',
                content: new OA\JsonContent(ref: '#/components/schemas/PaginatedPayments')
            ),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->query('per_page', 15), 100);
        $page = max((int) $request->query('page', 1), 1);
        $filters = array_filter([
            'status'    => $request->query('status'),
            'provider'  => $request->query('provider'),
            'from_date' => $request->query('from_date'),
            'to_date'   => $request->query('to_date'),
        ]);

        $result = $this->repository->paginate($perPage, $page, $filters);

        return response()->json([
            'data' => array_map(
                fn ($payment) => (new PaymentResource(PaymentResultDTO::fromAggregate($payment)))->resolve(),
                $result['data']
            ),
            'total' => $result['total'],
            'per_page' => $result['per_page'],
            'current_page' => $result['current_page'],
            'last_page' => $result['last_page'],
        ], Response::HTTP_OK);
    }

    #[OA\Post(
        path: '/payments',
        summary: 'Создать платёж',
        description: 'Создаёт платёж через YooKassa и возвращает ссылку для перехода на страницу оплаты',
        tags: ['Payments'],
        parameters: [
            new OA\Parameter(
                name: 'Idempotency-Key',
                in: 'header',
                description: 'Ключ идемпотентности (UUID). Если не передан — генерируется автоматически',
                required: false,
                schema: new OA\Schema(type: 'string', format: 'uuid', example: '550e8400-e29b-41d4-a716-446655440000')
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['amount', 'description', 'return_url'],
                properties: [
                    new OA\Property(property: 'amount', type: 'integer', description: 'Сумма в копейках (минимум 100 = 1 руб)', example: 50000, minimum: 100),
                    new OA\Property(property: 'description', type: 'string', description: 'Описание платежа', example: 'Оплата заказа №1234', maxLength: 255),
                    new OA\Property(property: 'return_url', type: 'string', format: 'uri', example: 'https://example.com/payment/success'),
                    new OA\Property(property: 'metadata', type: 'object', nullable: true),
                    new OA\Property(property: 'payment_method_type', type: 'string', nullable: true, enum: ['bank_card', 'yoo_money', 'sbp', 'sberbank', 'tinkoff_bank', 'cash']),
                    new OA\Property(property: 'confirmation_type', type: 'string', nullable: true, enum: ['redirect', 'embedded', 'qr', 'mobile_application']),
                    new OA\Property(property: 'save_payment_method', type: 'boolean', nullable: true, description: 'Сохранить метод для рекуррентных платежей'),
                    new OA\Property(property: 'payment_method_id', type: 'string', nullable: true, description: 'ID сохранённого метода для рекуррентного списания'),
                ]
            )
        ),
        responses: [
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
        ]
    )]
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
        ));

        return response()->json(new PaymentResource($result), Response::HTTP_CREATED);
    }

    #[OA\Get(
        path: '/payments/{id}',
        summary: 'Получить платёж',
        description: 'Возвращает информацию о платеже по его внутреннему ID',
        tags: ['Payments'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'ID платежа (ULID)', schema: new OA\Schema(type: 'string', example: '01HV9Z7BKQE4GNKR2XQVP0M8T')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Платёж найден', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/PaymentResponse')])),
            new OA\Response(response: 404, description: 'Платёж не найден', content: new OA\JsonContent(ref: '#/components/schemas/PaymentError')),
        ]
    )]
    public function show(string $id): JsonResponse
    {
        try {
            $paymentId = PaymentId::fromString($id);
        } catch (\InvalidArgumentException) {
            return response()->json(['error' => 'not_found', 'message' => 'Payment not found'], Response::HTTP_NOT_FOUND);
        }

        $payment = $this->repository->findById($paymentId);

        if ($payment === null) {
            return response()->json(['error' => 'not_found', 'message' => 'Payment not found'], Response::HTTP_NOT_FOUND);
        }

        return response()->json(new PaymentResource(PaymentResultDTO::fromAggregate($payment)), Response::HTTP_OK);
    }

    #[OA\Post(
        path: '/payments/{id}/cancel',
        summary: 'Отменить платёж',
        description: 'Отменяет платёж в статусе Pending. Платёж в терминальном статусе отменить нельзя.',
        tags: ['Payments'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'ID платежа (ULID)', schema: new OA\Schema(type: 'string')),
        ],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'reason', type: 'string', description: 'Причина отмены', example: 'Отменено пользователем', maxLength: 255),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Платёж отменён', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/PaymentResponse')])),
            new OA\Response(response: 404, description: 'Платёж не найден', content: new OA\JsonContent(ref: '#/components/schemas/PaymentError')),
            new OA\Response(response: 409, description: 'Невозможно отменить (терминальный статус)', content: new OA\JsonContent(ref: '#/components/schemas/PaymentError')),
        ]
    )]
    public function cancel(string $id): JsonResponse
    {
        $result = $this->bus->dispatch(new CancelPaymentCommand(
            paymentId: $id,
            reason: request()->input('reason', 'Отменено пользователем'),
        ));

        return response()->json(new PaymentResource($result), Response::HTTP_OK);
    }

    #[OA\Post(
        path: '/payments/{id}/refund',
        summary: 'Вернуть платёж',
        description: 'Создаёт возврат через YooKassa. Работает только для платежей в статусе Succeeded. Если сумма не указана — полный возврат.',
        tags: ['Payments'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'ID платежа (ULID)', schema: new OA\Schema(type: 'string')),
        ],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'amount', type: 'integer', description: 'Сумма возврата в копейках. Если не указана — полный возврат', example: 10000, minimum: 100, nullable: true),
                    new OA\Property(property: 'reason', type: 'string', description: 'Причина возврата', example: 'Возврат по заявке клиента', maxLength: 255),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Возврат создан', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/PaymentResponse')])),
            new OA\Response(response: 404, description: 'Платёж не найден', content: new OA\JsonContent(ref: '#/components/schemas/PaymentError')),
            new OA\Response(response: 409, description: 'Ошибка состояния платежа', content: new OA\JsonContent(ref: '#/components/schemas/PaymentError')),
            new OA\Response(response: 500, description: 'Ошибка YooKassa', content: new OA\JsonContent(ref: '#/components/schemas/PaymentError')),
        ]
    )]
    public function refund(string $id, RefundPaymentRequest $request): JsonResponse
    {
        $result = $this->bus->dispatch(new RefundPaymentCommand(
            paymentId: $id,
            amountKopecks: $request->integer('amount') ?: null,
            reason: $request->input('reason', ''),
            idempotencyKey: $request->header('Idempotency-Key'),
        ));

        return response()->json(new PaymentResource($result), Response::HTTP_OK);
    }

    #[OA\Post(
        path: '/payments/{id}/sync',
        summary: 'Синхронизировать статус с YooKassa',
        description: 'Запрашивает актуальный статус платежа у YooKassa и обновляет его в базе. Полезно для локальной разработки без публичного вебхука.',
        tags: ['Payments'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'ID платежа (ULID)', schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Статус синхронизирован', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/PaymentResponse')])),
            new OA\Response(response: 404, description: 'Платёж не найден', content: new OA\JsonContent(ref: '#/components/schemas/PaymentError')),
        ]
    )]
    public function sync(string $id): JsonResponse
    {
        $result = $this->bus->dispatch(new SyncPaymentCommand(paymentId: $id));

        return response()->json(new PaymentResource($result), Response::HTTP_OK);
    }

    #[OA\Get(
        path: '/payments/export',
        summary: 'Экспорт платежей в CSV',
        description: 'Стримит CSV-файл со всеми платежами, удовлетворяющими фильтрам. Поддерживает те же фильтры, что и GET /payments.',
        tags: ['Payments'],
        parameters: [
            new OA\Parameter(name: 'status',    in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'provider',  in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'from_date', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'to_date',   in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'CSV-файл', content: new OA\MediaType(mediaType: 'text/csv')),
        ]
    )]
    public function export(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $filters = array_filter([
            'status'    => $request->query('status'),
            'provider'  => $request->query('provider'),
            'from_date' => $request->query('from_date'),
            'to_date'   => $request->query('to_date'),
        ]);

        $filename = 'payments-' . now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($filters) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, ['id', 'status', 'provider', 'amount', 'currency', 'description', 'external_id', 'created_at']);

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
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    #[OA\Post(
        path: '/payments/{id}/retry',
        summary: 'Повторить отменённый платёж',
        description: 'Создаёт новый платёж с теми же параметрами, что у отменённого. Работает только для платежей в статусе `Cancelled`.',
        tags: ['Payments'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['return_url'],
                properties: [
                    new OA\Property(property: 'return_url', type: 'string', format: 'uri', example: 'https://example.com/success'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Новый платёж создан', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/PaymentResponse')])),
            new OA\Response(response: 404, description: 'Платёж не найден'),
            new OA\Response(response: 409, description: 'Платёж не в статусе Cancelled'),
        ]
    )]
    public function retry(string $id, Request $request): JsonResponse
    {
        $result = $this->bus->dispatch(new RetryPaymentCommand(
            paymentId:      $id,
            returnUrl:      (string) $request->input('return_url', ''),
            idempotencyKey: $request->header('Idempotency-Key') ?? (string) Str::uuid(),
        ));

        return response()->json(new PaymentResource($result), Response::HTTP_CREATED);
    }

    #[OA\Post(
        path: '/payments/{id}/resync',
        summary: 'Повторно отправить исходящее уведомление',
        description: 'Повторно отправляет POST-уведомление на `notification_url` из метаданных платежа. Полезно когда эндпоинт клиента временно не отвечал.',
        tags: ['Payments'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Уведомление отправлено (или notification_url не задан)'),
            new OA\Response(response: 404, description: 'Платёж не найден'),
        ]
    )]
    public function resync(string $id): JsonResponse
    {
        try {
            $paymentId = PaymentId::fromString($id);
        } catch (\InvalidArgumentException) {
            return response()->json(['error' => 'not_found', 'message' => 'Payment not found'], Response::HTTP_NOT_FOUND);
        }

        $payment = $this->repository->findById($paymentId);

        if ($payment === null) {
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
            $items = array_map(fn (array $item) => new ReceiptItemDTO(
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
