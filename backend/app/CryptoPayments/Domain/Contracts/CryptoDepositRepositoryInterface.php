<?php

declare(strict_types=1);

namespace App\CryptoPayments\Domain\Contracts;

use App\CryptoPayments\Domain\Aggregates\CryptoDeposit;
use App\CryptoPayments\Domain\Enums\CryptoAsset;
use App\CryptoPayments\Domain\ValueObjects\CryptoDepositId;

interface CryptoDepositRepositoryInterface
{
    public function save(CryptoDeposit $deposit): void;

    public function findById(CryptoDepositId $id): ?CryptoDeposit;

    public function findByPaymentId(string $paymentId): ?CryptoDeposit;

    /** @return CryptoDeposit[] */
    public function findAwaitingByAsset(CryptoAsset $asset): array;

    /**
     * Returns all Awaiting/Detected deposits older than $expiresAt.
     *
     * @return CryptoDeposit[]
     */
    public function findExpired(): array;

    /**
     * Returns deposit_address strings for all active (Awaiting/Detected) deposits on a given network.
     *
     * @return string[]
     */
    public function findActiveAddressesByNetwork(string $network): array;
}
