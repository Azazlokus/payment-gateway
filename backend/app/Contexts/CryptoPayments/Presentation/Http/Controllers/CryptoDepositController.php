<?php

declare(strict_types=1);

namespace App\Contexts\CryptoPayments\Presentation\Http\Controllers;

use App\Contexts\CryptoPayments\Application\Commands\CreateCryptoDeposit\CreateCryptoDepositCommand;
use App\Contexts\CryptoPayments\Application\Commands\CreateCryptoDeposit\CreateCryptoDepositHandler;
use App\Contexts\CryptoPayments\Application\Commands\CreateCryptoRefund\CreateCryptoRefundCommand;
use App\Contexts\CryptoPayments\Application\Commands\CreateCryptoRefund\CreateCryptoRefundHandler;
use App\Contexts\CryptoPayments\Application\DTOs\CryptoDepositResultDTO;
use App\Contexts\CryptoPayments\Domain\Aggregates\CryptoDeposit;
use App\Contexts\CryptoPayments\Domain\Contracts\CryptoDepositRepositoryInterface;
use App\Contexts\CryptoPayments\Domain\Enums\CryptoAsset;
use App\Contexts\CryptoPayments\Domain\Exceptions\CryptoDepositException;
use App\Contexts\CryptoPayments\Domain\ValueObjects\CryptoDepositId;
use App\Contexts\CryptoPayments\Presentation\Http\Requests\CreateCryptoDepositRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\Response;

final class CryptoDepositController extends Controller
{
    public function __construct(
        private readonly CreateCryptoDepositHandler $handler,
        private readonly CreateCryptoRefundHandler $refundHandler,
        private readonly CryptoDepositRepositoryInterface $deposits,
    ) {}

    public function store(CreateCryptoDepositRequest $request): JsonResponse
    {
        $command = new CreateCryptoDepositCommand(
            paymentId: (string) $request->validated('payment_id'),
            fiatAmountKopecks: (int) $request->validated('fiat_amount_kopecks'),
            asset: CryptoAsset::from((string) $request->validated('asset')),
        );

        $dto = $this->handler->handle($command);

        return response()->json($dto, Response::HTTP_CREATED);
    }

    public function show(string $id): JsonResponse
    {
        $deposit = $this->deposits->findById(CryptoDepositId::fromString($id));

        if (! $deposit instanceof CryptoDeposit) {
            return response()->json(['code' => 'not_found', 'message' => 'Crypto deposit not found.'], Response::HTTP_NOT_FOUND);
        }

        return response()->json(CryptoDepositResultDTO::fromAggregate($deposit));
    }

    public function refund(string $id, Request $request): JsonResponse
    {
        $request->validate(['to_address' => ['required', 'string', 'max:255']]);

        try {
            $refundId = $this->refundHandler->handle(
                new CreateCryptoRefundCommand(
                    depositId: $id,
                    toAddress: (string) $request->input('to_address'),
                )
            );
        } catch (CryptoDepositException $e) {
            return response()->json(
                ['code' => 'invalid_state', 'message' => $e->getMessage()],
                Response::HTTP_CONFLICT
            );
        }

        return response()->json(['refund_id' => $refundId->toString()], Response::HTTP_CREATED);
    }
}
