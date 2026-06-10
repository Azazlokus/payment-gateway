<?php

declare(strict_types=1);

namespace App\Payments\Presentation\Http\Controllers;

use App\Payments\Application\Bus\CommandBus;
use App\Payments\Application\Commands\CreatePayment\CreatePaymentCommand;
use App\Payments\Application\DTOs\CreatePaymentOptionsDTO;
use App\Payments\Infrastructure\Persistence\Models\PaymentModel;
use App\Payments\Presentation\Http\Resources\PaymentResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Управление рекуррентными платежами.
 *
 * Рекуррентный платёж — это списание без редиректа клиента.
 * Для этого нужен payment_method_id, полученный при первом платеже
 * с save_payment_method=true.
 */
final class RecurringController extends Controller
{
    public function __construct(private readonly CommandBus $bus) {}

    /**
     * GET /api/v1/recurring/methods
     * Список уникальных сохранённых методов оплаты.
     * Группирует по payment_method_id, возвращает последний платёж для каждого метода.
     */
    public function methods(): JsonResponse
    {
        $methods = PaymentModel::query()
            ->whereNotNull('payment_method_id')
            ->whereIn('status', ['Succeeded'])
            ->select(['id', 'payment_method_id', 'provider', 'amount', 'currency', 'description', 'created_at'])
            ->orderByDesc('created_at')
            ->get()
            // Дедупликация: один метод — одна запись (самая свежая)
            ->unique('payment_method_id')
            ->map(fn ($p) => [
                'payment_method_id' => $p->payment_method_id,
                'provider' => $p->provider,
                'last_payment_id' => $p->id,
                'last_amount' => $p->amount,
                'currency' => $p->currency,
                'last_used_at' => $p->created_at?->toIso8601String(),
            ])
            ->values();

        return response()->json(['data' => $methods]);
    }

    /**
     * POST /api/v1/recurring/charge
     * Списать с сохранённого метода без редиректа клиента.
     *
     * Body: { payment_method_id, amount, description, return_url? }
     */
    public function charge(Request $request): JsonResponse
    {
        $data = $request->validate([
            'payment_method_id' => ['required', 'string'],
            'amount' => ['required', 'integer', 'min:100'],
            'description' => ['required', 'string', 'max:255'],
            'return_url' => ['sometimes', 'url'],
            'metadata' => ['sometimes', 'nullable', 'array'],
        ]);

        // Определяем провайдера по последнему платежу с этим методом
        $lastPayment = PaymentModel::where('payment_method_id', $data['payment_method_id'])
            ->where('status', 'Succeeded')
            ->latest()
            ->first();

        if ($lastPayment === null) {
            return response()->json([
                'error' => 'invalid_payment_method',
                'message' => 'Payment method not found or not usable',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $result = $this->bus->dispatch(new CreatePaymentCommand(
            amountKopecks: $data['amount'],
            description: $data['description'],
            returnUrl: $data['return_url'] ?? '',
            idempotencyKey: $request->header('Idempotency-Key') ?? (string) Str::uuid(),
            userId: null,
            metadata: $data['metadata'] ?? [],
            options: new CreatePaymentOptionsDTO(
                confirmationType: 'redirect',
                paymentMethodId: $data['payment_method_id'],
            ),
            provider: $lastPayment->provider,
        ));

        return response()->json(new PaymentResource($result), Response::HTTP_CREATED);
    }
}
