<?php

declare(strict_types=1);

namespace App\Contexts\CryptoPayments\Application\Commands\CreateCryptoDeposit;

use App\Contexts\CryptoPayments\Application\DTOs\CryptoDepositResultDTO;
use App\Contexts\CryptoPayments\Domain\Aggregates\CryptoDeposit;
use App\Contexts\CryptoPayments\Domain\Contracts\CryptoDepositRepositoryInterface;
use App\Contexts\CryptoPayments\Domain\Contracts\PriceOracleInterface;
use App\Contexts\CryptoPayments\Domain\Enums\DepositMode;
use App\Contexts\CryptoPayments\Domain\ValueObjects\CryptoAddress;
use App\Contexts\CryptoPayments\Domain\ValueObjects\CryptoDepositId;
use App\Contexts\CryptoPayments\Domain\ValueObjects\Memo;
use App\Contexts\CryptoPayments\Domain\ValueObjects\NativeCryptoAmount;
use App\Contexts\CryptoPayments\Infrastructure\Blockchain\BlockchainClientRegistry;
use App\Contexts\CryptoPayments\Infrastructure\Observability\CryptoMetricsService;
use App\Contexts\Payments\Infrastructure\Observability\PaymentLogger;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

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
            'payment_id' => $command->paymentId,
            'fiat_amount_kopecks' => $command->fiatAmountKopecks,
            'asset' => $command->asset->value,
        ]);

        return DB::transaction(function () use ($command): CryptoDepositResultDTO {
            $cryptoUnits = $this->priceOracle->kopecksToCryptoUnits(
                $command->fiatAmountKopecks,
                $command->asset,
            );

            $expectedAmount = NativeCryptoAmount::of($cryptoUnits, $command->asset);

            $client = $this->blockchain->getForAsset($command->asset);
            $ttlMinutes = (int) config('crypto.deposit_ttl_minutes', 20);
            $expiresAt = new DateTimeImmutable("+{$ttlMinutes} minutes");

            if ($client->depositMode() === DepositMode::Memo) {
                $depositAddress = $client->masterDepositAddress();
                $memo = Memo::generate();
            } else {
                $usedAddresses = $this->deposits->findActiveAddressesByNetwork($client->network());
                $pool = $client->depositAddressPool();
                $available = array_filter($pool, fn (string $addr) => ! in_array($addr, $usedAddresses, true));

                if (empty($available)) {
                    throw new RuntimeException(
                        "No available deposit addresses for {$command->asset->value}. Please add more addresses to the pool."
                    );
                }

                $depositAddress = CryptoAddress::fromString(reset($available));
                $memo = null;
            }

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
                'asset' => $command->asset->value,
                'memo' => $memo?->toString(),
            ]);

            return CryptoDepositResultDTO::fromAggregate($deposit);
        });
    }
}
