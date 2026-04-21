<?php

declare(strict_types=1);

namespace App\CryptoPayments\Domain\Contracts;

use App\CryptoPayments\Domain\Enums\CryptoAsset;
use App\CryptoPayments\Domain\ValueObjects\Memo;
use App\CryptoPayments\Domain\ValueObjects\NativeCryptoAmount;
use App\CryptoPayments\Domain\ValueObjects\TonAddress;
use App\CryptoPayments\Domain\ValueObjects\TransactionResult;
use DateTimeImmutable;

interface BlockchainClientInterface
{
    public function network(): string;

    /** @return CryptoAsset[] */
    public function supportedAssets(): array;

    public function masterDepositAddress(): TonAddress;

    /**
     * Queries blockchain for confirmed incoming transaction matching memo+asset+minAmount since given time.
     * Returns null if not found yet.
     */
    public function findIncomingTransaction(
        Memo $memo,
        CryptoAsset $asset,
        NativeCryptoAmount $expectedAmount,
        DateTimeImmutable $since,
    ): ?TransactionResult;

    /**
     * Batch version: given list of memos, returns map of memo→TransactionResult for found ones.
     * More efficient — one API call for all pending deposits on same address.
     *
     * @param  Memo[] $memos
     * @return array<string, TransactionResult>  key = memo string
     */
    public function findIncomingTransactionsBatch(
        array $memos,
        CryptoAsset $asset,
        DateTimeImmutable $since,
    ): array;
}
