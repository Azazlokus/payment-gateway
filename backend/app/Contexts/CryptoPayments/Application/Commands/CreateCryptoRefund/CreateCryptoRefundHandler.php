<?php

declare(strict_types=1);

namespace App\Contexts\CryptoPayments\Application\Commands\CreateCryptoRefund;

use App\Contexts\CryptoPayments\Domain\Aggregates\CryptoRefundRequest;
use App\Contexts\CryptoPayments\Domain\Contracts\CryptoDepositRepositoryInterface;
use App\Contexts\CryptoPayments\Domain\Contracts\CryptoRefundRepositoryInterface;
use App\Contexts\CryptoPayments\Domain\Enums\CryptoDepositStatus;
use App\Contexts\CryptoPayments\Domain\Exceptions\CryptoDepositException;
use App\Contexts\CryptoPayments\Domain\ValueObjects\CryptoAddress;
use App\Contexts\CryptoPayments\Domain\ValueObjects\CryptoDepositId;
use App\Contexts\CryptoPayments\Domain\ValueObjects\CryptoRefundId;
use Illuminate\Support\Facades\DB;

final readonly class CreateCryptoRefundHandler
{
    public function __construct(
        private CryptoDepositRepositoryInterface $deposits,
        private CryptoRefundRepositoryInterface $refunds,
    ) {}

    public function handle(CreateCryptoRefundCommand $command): CryptoRefundId
    {
        return DB::transaction(function () use ($command): CryptoRefundId {
            // Невалидный формат id (не ULID) → бросаем доменное исключение,
            // чтобы контроллер вернул 409, а не 500 от InvalidArgumentException.
            try {
                $depositId = CryptoDepositId::fromString($command->depositId);
            } catch (\InvalidArgumentException) {
                throw new CryptoDepositException("Deposit {$command->depositId} not found");
            }

            $deposit = $this->deposits->findById($depositId);

            if ($deposit === null) {
                throw new CryptoDepositException("Deposit {$command->depositId} not found");
            }

            if ($deposit->status() !== CryptoDepositStatus::Confirmed) {
                throw new CryptoDepositException(
                    "Cannot refund deposit {$command->depositId}: status is {$deposit->status()->value}, must be confirmed"
                );
            }

            if ($this->refunds->existsForDeposit($command->depositId)) {
                throw new CryptoDepositException(
                    "A refund request already exists for deposit {$command->depositId}"
                );
            }

            $refund = CryptoRefundRequest::create(
                depositId: $command->depositId,
                toAddress: CryptoAddress::fromString($command->toAddress),
                amount: $deposit->actualAmount() ?? $deposit->expectedAmount(),
                asset: $deposit->asset(),
            );

            $this->refunds->save($refund);

            return $refund->id();
        });
    }
}
