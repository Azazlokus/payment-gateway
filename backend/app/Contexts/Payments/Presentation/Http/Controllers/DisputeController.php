<?php

declare(strict_types=1);

namespace App\Contexts\Payments\Presentation\Http\Controllers;

use App\Contexts\Payments\Domain\Aggregates\Dispute;
use App\Contexts\Payments\Domain\Contracts\DisputeRepositoryInterface;
use App\Contexts\Payments\Domain\Contracts\PaymentRepositoryInterface;
use App\Contexts\Payments\Domain\Enums\DisputeStatus;
use App\Contexts\Payments\Domain\ValueObjects\DisputeId;
use App\Contexts\Payments\Domain\ValueObjects\Money;
use App\Contexts\Payments\Domain\ValueObjects\PaymentId;
use App\Contexts\Payments\Infrastructure\Observability\AuditLogger;
use App\Contexts\Payments\Infrastructure\Observability\MetricsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;

final class DisputeController extends Controller
{
    public function __construct(
        private readonly DisputeRepositoryInterface $disputes,
        private readonly PaymentRepositoryInterface $payments,
        private readonly MetricsService $metrics,
        private readonly AuditLogger $auditLogger,
    ) {}

    #[OA\Get(
        path: '/payments/{paymentId}/disputes',
        summary: 'Список диспутов по платежу',
        tags: ['Disputes'],
        parameters: [
            new OA\Parameter(name: 'paymentId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'ulid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Список диспутов', content: new OA\JsonContent(
                properties: [new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/DisputeResponse'))]
            )),
            new OA\Response(response: 404, description: 'Платёж не найден', content: new OA\JsonContent(ref: '#/components/schemas/PaymentError')),
        ]
    )]
    public function index(string $paymentId): JsonResponse
    {
        try {
            $id = PaymentId::fromString($paymentId);
        } catch (\InvalidArgumentException) {
            return $this->notFound();
        }

        $disputes = $this->disputes->findByPaymentId($id);

        return response()->json([
            'data' => array_map(fn (Dispute $d) => $this->format($d), $disputes),
        ]);
    }

    #[OA\Post(
        path: '/payments/{paymentId}/disputes',
        summary: 'Открыть диспут по платежу',
        description: 'Регистрирует чарджбэк/диспут по платежу. Статус при создании — `Filed`.',
        tags: ['Disputes'],
        parameters: [
            new OA\Parameter(name: 'paymentId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'ulid')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['amount', 'reason'],
                properties: [
                    new OA\Property(property: 'amount', type: 'integer', description: 'Оспариваемая сумма в копейках', example: 50000, minimum: 1),
                    new OA\Property(property: 'reason', type: 'string', description: 'Основание диспута', example: 'Товар не получен', maxLength: 500),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Диспут создан', content: new OA\JsonContent(ref: '#/components/schemas/DisputeResponse')),
            new OA\Response(response: 404, description: 'Платёж не найден', content: new OA\JsonContent(ref: '#/components/schemas/PaymentError')),
            new OA\Response(response: 422, description: 'Ошибка валидации', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
        ]
    )]
    public function store(string $paymentId, Request $request): JsonResponse
    {
        $request->validate([
            'amount' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        try {
            $id = PaymentId::fromString($paymentId);
        } catch (\InvalidArgumentException) {
            return $this->notFound();
        }

        $payment = $this->payments->findById($id);

        if ($payment === null) {
            return $this->notFound();
        }

        $dispute = Dispute::file(
            id: DisputeId::generate(),
            paymentId: $id,
            amount: Money::ofRub($request->integer('amount')),
            reason: $request->string('reason')->toString(),
        );

        $this->disputes->save($dispute);
        $this->metrics->disputeFiled($payment->provider());
        $this->auditLogger->log('dispute.filed', 'payment', $paymentId, [], $request);

        return response()->json($this->format($dispute), Response::HTTP_CREATED);
    }

    #[OA\Get(
        path: '/disputes/{id}',
        summary: 'Получить диспут',
        tags: ['Disputes'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'ulid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Диспут найден', content: new OA\JsonContent(ref: '#/components/schemas/DisputeResponse')),
            new OA\Response(response: 404, description: 'Диспут не найден', content: new OA\JsonContent(ref: '#/components/schemas/PaymentError')),
        ]
    )]
    public function show(string $id): JsonResponse
    {
        try {
            $disputeId = DisputeId::fromString($id);
        } catch (\InvalidArgumentException) {
            return $this->notFound();
        }

        $dispute = $this->disputes->findById($disputeId);

        if ($dispute === null) {
            return $this->notFound();
        }

        return response()->json($this->format($dispute));
    }

    #[OA\Post(
        path: '/disputes/{id}/resolve',
        summary: 'Разрешить диспут',
        description: 'Помечает диспут как `Won` (победа) или `Lost` (проигрыш). Диспут уже в статусе Won/Lost повторно разрешить нельзя — 409.',
        tags: ['Disputes'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'ulid')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['resolution'],
                properties: [
                    new OA\Property(property: 'resolution', type: 'string', enum: ['Won', 'Lost'], description: 'Исход диспута'),
                    new OA\Property(property: 'note', type: 'string', nullable: true, description: 'Комментарий', maxLength: 500),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Диспут разрешён', content: new OA\JsonContent(ref: '#/components/schemas/DisputeResponse')),
            new OA\Response(response: 404, description: 'Диспут не найден', content: new OA\JsonContent(ref: '#/components/schemas/PaymentError')),
            new OA\Response(response: 409, description: 'Диспут уже разрешён', content: new OA\JsonContent(ref: '#/components/schemas/PaymentError')),
            new OA\Response(response: 422, description: 'Ошибка валидации', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
        ]
    )]
    public function resolve(string $id, Request $request): JsonResponse
    {
        $request->validate([
            'resolution' => ['required', 'in:Won,Lost'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $disputeId = DisputeId::fromString($id);
        } catch (\InvalidArgumentException) {
            return $this->notFound();
        }

        $dispute = $this->disputes->findById($disputeId);

        if ($dispute === null) {
            return $this->notFound();
        }

        $resolution = $request->string('resolution')->toString();
        $note = $request->string('note')->toString() ?: null;

        if ($resolution === DisputeStatus::Won->value) {
            $dispute->markAsWon($note);
        } else {
            $dispute->markAsLost($note);
        }

        $this->disputes->save($dispute);
        $this->metrics->disputeResolved($resolution);
        $this->auditLogger->log('dispute.resolved', 'dispute', $id, ['resolution' => $resolution], $request);

        return response()->json($this->format($dispute));
    }

    // ─── Private ─────────────────────────────────────────────────────────────

    /** @return array<string, mixed> */
    private function format(Dispute $d): array
    {
        return [
            'id' => $d->id()->toString(),
            'payment_id' => $d->paymentId()->toString(),
            'status' => $d->status()->value,
            'amount' => $d->amount()->amount(),
            'currency' => $d->amount()->currency()->value,
            'reason' => $d->reason(),
            'note' => $d->note(),
        ];
    }

    private function notFound(): JsonResponse
    {
        return response()->json([
            'code' => 'not_found',
            'message' => 'Resource not found',
        ], Response::HTTP_NOT_FOUND);
    }
}
