<?php

declare(strict_types=1);

namespace App\CryptoPayments\Application\Commands\CreateCryptoRefund;

use App\CryptoPayments\Domain\Aggregates\CryptoRefundRequest;
use App\CryptoPayments\Domain\Contracts\CryptoDepositRepositoryInterface;
use App\CryptoPayments\Domain\Contracts\CryptoRefundRepositoryInterface;
use App\CryptoPayments\Domain\Enums\CryptoDepositStatus;
use App\CryptoPayments\Domain\Exceptions\CryptoDepositException;
use App\CryptoPayments\Domain\ValueObjects\CryptoAddress;
use App\CryptoPayments\Domain\ValueObjects\CryptoDepositId;
use App\CryptoPayments\Domain\ValueObjects\CryptoRefundId;
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
            $deposit = $this->deposits->findById(CryptoDepositId::fromString($command->depositId));

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
