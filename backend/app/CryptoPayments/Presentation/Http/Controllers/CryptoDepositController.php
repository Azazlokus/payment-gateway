<?php

declare(strict_types=1);

namespace App\CryptoPayments\Presentation\Http\Controllers;

use App\CryptoPayments\Application\Commands\CreateCryptoDeposit\CreateCryptoDepositCommand;
use App\CryptoPayments\Application\Commands\CreateCryptoDeposit\CreateCryptoDepositHandler;
use App\CryptoPayments\Application\DTOs\CryptoDepositResultDTO;
use App\CryptoPayments\Domain\Contracts\CryptoDepositRepositoryInterface;
use App\CryptoPayments\Domain\Enums\CryptoAsset;
use App\CryptoPayments\Domain\ValueObjects\CryptoDepositId;
use App\CryptoPayments\Presentation\Http\Requests\CreateCryptoDepositRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\Response;

final class CryptoDepositController extends Controller
{
    public function __construct(
        private readonly CreateCryptoDepositHandler $handler,
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

        if ($deposit === null) {
            return response()->json(['code' => 'not_found', 'message' => 'Crypto deposit not found.'], Response::HTTP_NOT_FOUND);
        }

        return response()->json(CryptoDepositResultDTO::fromAggregate($deposit));
    }
}
