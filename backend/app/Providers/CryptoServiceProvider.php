<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contexts\CryptoPayments\Application\ACL\CryptoDepositToPaymentAdapter;
use App\Contexts\CryptoPayments\Application\Commands\CreateCryptoRefund\CreateCryptoRefundHandler;
use App\Contexts\CryptoPayments\Domain\Contracts\CryptoDepositRepositoryInterface;
use App\Contexts\CryptoPayments\Domain\Contracts\CryptoRefundRepositoryInterface;
use App\Contexts\CryptoPayments\Domain\Contracts\PriceOracleInterface;
use App\Contexts\CryptoPayments\Infrastructure\Blockchain\BitcoinBlockchainClient;
use App\Contexts\CryptoPayments\Infrastructure\Blockchain\BlockchainClientRegistry;
use App\Contexts\CryptoPayments\Infrastructure\Blockchain\TonBlockchainClient;
use App\Contexts\CryptoPayments\Infrastructure\Blockchain\TronBlockchainClient;
use App\Contexts\CryptoPayments\Infrastructure\Observability\CryptoMetricsService;
use App\Contexts\CryptoPayments\Infrastructure\Persistence\EloquentCryptoDepositRepository;
use App\Contexts\CryptoPayments\Infrastructure\Persistence\EloquentCryptoRefundRepository;
use App\Contexts\CryptoPayments\Infrastructure\Pricing\CoinGeckoPriceOracle;
use App\Contexts\Payments\Domain\Contracts\PaymentRepositoryInterface;
use App\Contexts\Payments\Infrastructure\Observability\MetricsService;
use App\Contexts\Payments\Infrastructure\Observability\PaymentLogger;
use Illuminate\Support\ServiceProvider;

class CryptoServiceProvider extends ServiceProvider
{
    #[\Override]
    public function register(): void
    {
        $this->app->singleton(CryptoDepositRepositoryInterface::class, EloquentCryptoDepositRepository::class);

        $this->app->singleton(PriceOracleInterface::class, CoinGeckoPriceOracle::class);

        $this->app->singleton(CryptoMetricsService::class);

        $this->app->singleton(TonBlockchainClient::class, fn () => new TonBlockchainClient(
            masterAddress: (string) config('crypto.ton.master_address'),
            apiKey: (string) config('crypto.ton.api_key', ''),
            apiUrl: (string) config('crypto.ton.api_url', 'https://toncenter.com/api/v2'),
            apiV3Url: (string) config('crypto.ton.api_v3_url', 'https://toncenter.com/api/v3'),
            usdtJettonMaster: (string) config('crypto.ton.usdt_jetton_master', 'EQCxE6mUtQJKFnGfaROTKOt1lZbDiiX1kCixRv7Nw2Id_sDs'),
            logger: $this->app->make(PaymentLogger::class),
        ));

        $this->app->singleton(TronBlockchainClient::class, fn () => new TronBlockchainClient(
            apiUrl: (string) config('crypto.tron.api_url', 'https://api.trongrid.io'),
            apiKey: (string) config('crypto.tron.api_key', ''),
            usdtContract: (string) config('crypto.tron.usdt_contract', 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t'),
            addressPool: (array) config('crypto.tron.deposit_addresses', []),
            logger: $this->app->make(PaymentLogger::class),
        ));

        $this->app->singleton(BitcoinBlockchainClient::class, fn () => new BitcoinBlockchainClient(
            apiUrl: (string) config('crypto.bitcoin.api_url', 'https://mempool.space/api'),
            addressPool: (array) config('crypto.bitcoin.deposit_addresses', []),
            logger: $this->app->make(PaymentLogger::class),
        ));

        $this->app->singleton(BlockchainClientRegistry::class, function (): BlockchainClientRegistry {
            $registry = new BlockchainClientRegistry;
            $registry->register($this->app->make(TonBlockchainClient::class));
            $registry->register($this->app->make(TronBlockchainClient::class));
            $registry->register($this->app->make(BitcoinBlockchainClient::class));

            return $registry;
        });

        $this->app->singleton(CryptoRefundRepositoryInterface::class, EloquentCryptoRefundRepository::class);

        $this->app->singleton(CreateCryptoRefundHandler::class, fn () => new CreateCryptoRefundHandler(
            deposits: $this->app->make(CryptoDepositRepositoryInterface::class),
            refunds: $this->app->make(CryptoRefundRepositoryInterface::class),
        ));

        $this->app->singleton(CryptoDepositToPaymentAdapter::class, fn () => new CryptoDepositToPaymentAdapter(
            payments: $this->app->make(PaymentRepositoryInterface::class),
            metrics: $this->app->make(MetricsService::class),
            logger: $this->app->make(PaymentLogger::class),
        ));
    }

    public function boot(): void {}
}
