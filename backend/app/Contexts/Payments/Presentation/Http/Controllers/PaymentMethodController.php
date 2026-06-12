<?php

declare(strict_types=1);

namespace App\Contexts\Payments\Presentation\Http\Controllers;

use App\Contexts\Payments\Application\Bus\CommandBus;
use App\Contexts\Payments\Application\Commands\ChargeToken\ChargeTokenCommand;
use App\Contexts\Payments\Application\Commands\DeletePaymentMethod\DeletePaymentMethodCommand;
use App\Contexts\Payments\Application\Commands\TokenizePaymentMethod\TokenizePaymentMethodCommand;
use App\Contexts\Payments\Domain\Aggregates\PaymentMethod;
use App\Contexts\Payments\Domain\Contracts\PaymentMethodRepositoryInterface;
use App\Contexts\Payments\Presentation\Http\Resources\PaymentMethodResource;
use App\Contexts\Payments\Presentation\Http\Resources\PaymentResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final class PaymentMethodController extends Controller
{
    public function __construct(
        private readonly CommandBus $bus,
        private readonly PaymentMethodRepositoryInterface $repository,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $customerId = $request->string('customer_id')->toString();

        if ($customerId === '') {
            return response()->json([
                'code' => 'validation_error',
                'message' => 'customer_id is required',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $methods = $this->repository->findByCustomerId($customerId);

        return response()->json([
            'data' => array_map(
                fn (PaymentMethod $m) => new PaymentMethodResource($m)->resolve(),
                $methods,
            ),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $method = $this->bus->dispatch(new TokenizePaymentMethodCommand(
            paymentId: $request->string('payment_id')->toString(),
            customerId: $request->string('customer_id')->toString(),
        ));

        return response()->json(
            new PaymentMethodResource($method),
            Response::HTTP_CREATED,
        );
    }

    public function charge(string $id, Request $request): JsonResponse
    {
        $result = $this->bus->dispatch(new ChargeTokenCommand(
            paymentMethodId: $id,
            amountKopecks: $request->integer('amount'),
            description: $request->string('description')->toString(),
            returnUrl: $request->string('return_url')->toString(),
            idempotencyKey: $request->header('Idempotency-Key') ?? (string) Str::uuid(),
            userId: $request->user()?->id,
            metadata: $request->input('metadata', []),
        ));

        return response()->json(new PaymentResource($result), Response::HTTP_CREATED);
    }

    public function destroy(string $id): JsonResponse
    {
        $this->bus->dispatch(new DeletePaymentMethodCommand(paymentMethodId: $id));

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
