<?php

declare(strict_types=1);

namespace App\CryptoPayments\Infrastructure\Persistence;

use App\CryptoPayments\Domain\Aggregates\CryptoDeposit;
use App\CryptoPayments\Domain\Contracts\CryptoDepositRepositoryInterface;
use App\CryptoPayments\Domain\Enums\CryptoAsset;
use App\CryptoPayments\Domain\Enums\CryptoDepositStatus;
use App\CryptoPayments\Domain\ValueObjects\CryptoDepositId;
use App\CryptoPayments\Domain\ValueObjects\Memo;
use App\CryptoPayments\Domain\ValueObjects\NativeCryptoAmount;
use App\CryptoPayments\Domain\ValueObjects\TonAddress;
use App\CryptoPayments\Domain\ValueObjects\TxHash;
use App\CryptoPayments\Infrastructure\Persistence\Models\CryptoDepositModel;
use App\Payments\Infrastructure\Persistence\Models\PaymentEventModel;
use DateTimeImmutable;

final class EloquentCryptoDepositRepository implements CryptoDepositRepositoryInterface
{
    public function save(CryptoDeposit $deposit): void
    {
        CryptoDepositModel::updateOrCreate(
            ['id' => $deposit->id()->toString()],
            [
                'payment_id'          => $deposit->paymentId(),
                'status'              => $deposit->status()->value,
                'asset'               => $deposit->asset()->value,
                'expected_units'      => $deposit->expectedAmount()->units(),
                'actual_units'        => $deposit->actualAmount()?->units(),
                'fiat_amount_kopecks' => $deposit->fiatAmountKopecks(),
                'deposit_address'     => $deposit->depositAddress()->toString(),
                'memo'                => $deposit->memo()->toString(),
                'tx_hash'             => $deposit->txHash()?->toString(),
                'expires_at'          => $deposit->expiresAt()->format('Y-m-d H:i:s'),
                'created_at_ts'       => $deposit->createdAtTimestamp(),
            ]
        );

        foreach ($deposit->pullDomainEvents() as $event) {
            PaymentEventModel::create([
                'payment_id'  => $deposit->paymentId(),
                'event_id'    => $event->eventId,
                'event_name'  => $event->eventName(),
                'event_data'  => $event->toArray(),
                'occurred_at' => $event->occurredAt,
            ]);
        }
    }

    public function findById(CryptoDepositId $id): ?CryptoDeposit
    {
        $model = CryptoDepositModel::find($id->toString());

        return $model ? $this->hydrate($model) : null;
    }

    public function findByPaymentId(string $paymentId): ?CryptoDeposit
    {
        $model = CryptoDepositModel::where('payment_id', $paymentId)->first();

        return $model ? $this->hydrate($model) : null;
    }

    /** @return CryptoDeposit[] */
    public function findAwaitingByAsset(CryptoAsset $asset): array
    {
        /** @var CryptoDepositModel[] $models */
        $models = CryptoDepositModel::where('asset', $asset->value)
            ->whereIn('status', [CryptoDepositStatus::Awaiting->value, CryptoDepositStatus::Detected->value])
            ->get()
            ->all();

        return array_map(fn (CryptoDepositModel $m) => $this->hydrate($m), $models);
    }

    /** @return CryptoDeposit[] */
    public function findExpired(): array
    {
        /** @var CryptoDepositModel[] $models */
        $models = CryptoDepositModel::whereIn('status', [CryptoDepositStatus::Awaiting->value, CryptoDepositStatus::Detected->value])
            ->where('expires_at', '<', now())
            ->get()
            ->all();

        return array_map(fn (CryptoDepositModel $m) => $this->hydrate($m), $models);
    }

    private function hydrate(CryptoDepositModel $model): CryptoDeposit
    {
        /** @var string $id */
        $id = $model->getKey();

        /** @var CryptoDepositStatus $status */
        $status = $model->status;

        /** @var CryptoAsset $asset */
        $asset = $model->asset;

        $txHash = $model->tx_hash !== null ? TxHash::fromString($model->tx_hash) : null;

        $actualAmount = null;
        if ($model->actual_units !== null) {
            $actualAmount = NativeCryptoAmount::of((int) $model->actual_units, $asset);
        }

        return CryptoDeposit::restore(
            id: CryptoDepositId::fromString($id),
            paymentId: (string) $model->payment_id,
            status: $status,
            asset: $asset,
            expectedAmount: NativeCryptoAmount::of((int) $model->expected_units, $asset),
            fiatAmountKopecks: (int) $model->fiat_amount_kopecks,
            depositAddress: TonAddress::fromString((string) $model->deposit_address),
            memo: Memo::fromString((string) $model->memo),
            expiresAt: new DateTimeImmutable((string) $model->expires_at),
            createdAtTimestamp: (int) $model->created_at_ts,
            txHash: $txHash,
            actualAmount: $actualAmount,
        );
    }
}
