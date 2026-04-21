<?php

declare(strict_types=1);

namespace App\CryptoPayments\Application\Commands\CreateCryptoDeposit;

use App\CryptoPayments\Application\DTOs\CryptoDepositResultDTO;
use App\CryptoPayments\Domain\Aggregates\CryptoDeposit;
use App\CryptoPayments\Domain\Contracts\CryptoDepositRepositoryInterface;
use App\CryptoPayments\Domain\Contracts\PriceOracleInterface;
use App\CryptoPayments\Domain\Enums\CryptoAsset;
use App\CryptoPayments\Domain\ValueObjects\CryptoDepositId;
use App\CryptoPayments\Domain\ValueObjects\Memo;
use App\CryptoPayments\Domain\ValueObjects\NativeCryptoAmount;
use App\CryptoPayments\Infrastructure\Blockchain\BlockchainClientRegistry;
use App\CryptoPayments\Infrastructure\Observability\CryptoMetricsService;
use App\Payments\Infrastructure\Observability\PaymentLogger;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

final readonly class CreateCryptoDepositHandler
{
    public function __construct(
        private CryptoDepositRepositoryInterface $deposits,
        private BlockchainClientRegistry $blockchain,
        private PriceOracleInterface $priceOracle,
        private CryptoMetricsService $metrics,
        private PaymentLogger $logger,
    ) {}

    public function handle(CreateCryptoDepositCommand $command): CryptoDepositResultDTO
    {
        $this->logger->info('Creating crypto deposit', [
            'payment_id'         => $command->paymentId,
            'fiat_amount_kopecks' => $command->fiatAmountKopecks,
            'asset'              => $command->asset->value,
        ]);

        return DB::transaction(function () use ($command): CryptoDepositResultDTO {
            $cryptoUnits = $this->priceOracle->kopecksToCryptoUnits(
                $command->fiatAmountKopecks,
                $command->asset,
            );

            $expectedAmount = match ($command->asset) {
                CryptoAsset::TON      => NativeCryptoAmount::ofNanotons($cryptoUnits),
                CryptoAsset::USDT_TON => NativeCryptoAmount::ofMicroUsdt($cryptoUnits),
            };

            $client         = $this->blockchain->getForAsset($command->asset);
            $depositAddress = $client->masterDepositAddress();
            $memo           = Memo::generate();

            $ttlMinutes = (int) config('crypto.ton.deposit_ttl_minutes', 20);
            $expiresAt  = new DateTimeImmutable("+{$ttlMinutes} minutes");

            $deposit = CryptoDeposit::create(
                id: CryptoDepositId::generate(),
                paymentId: $command->paymentId,
                asset: $command->asset,
                expectedAmount: $expectedAmount,
                fiatAmountKopecks: $command->fiatAmountKopecks,
                depositAddress: $depositAddress,
                memo: $memo,
                expiresAt: $expiresAt,
            );

            $this->deposits->save($deposit);

            $this->metrics->depositCreated($command->asset->value);

            $this->logger->info('Crypto deposit created', [
                'deposit_id' => $deposit->id()->toString(),
                'payment_id' => $command->paymentId,
                'asset'      => $command->asset->value,
                'memo'       => $memo->toString(),
            ]);

            return CryptoDepositResultDTO::fromAggregate($deposit);
        });
    }
}
