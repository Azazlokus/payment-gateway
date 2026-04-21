<?php

declare(strict_types=1);

namespace App\CryptoPayments\Infrastructure\Blockchain;

use App\CryptoPayments\Domain\Contracts\BlockchainClientInterface;
use App\CryptoPayments\Domain\Enums\CryptoAsset;
use RuntimeException;

final class BlockchainClientRegistry
{
    /** @var array<string, BlockchainClientInterface> */
    private array $clients = [];

    public function register(BlockchainClientInterface $client): void
    {
        $this->clients[$client->network()] = $client;
    }

    public function get(string $network): BlockchainClientInterface
    {
        if (! isset($this->clients[$network])) {
            throw new RuntimeException("No blockchain client registered for network: {$network}");
        }

        return $this->clients[$network];
    }

    public function getForAsset(CryptoAsset $asset): BlockchainClientInterface
    {
        return $this->get($asset->network());
    }
}
