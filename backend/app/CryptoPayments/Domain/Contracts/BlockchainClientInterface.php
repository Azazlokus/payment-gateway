<?php

declare(strict_types=1);

namespace App\CryptoPayments\Domain\Contracts;

use App\CryptoPayments\Domain\Enums\CryptoAsset;
use App\CryptoPayments\Domain\Enums\DepositMode;
use App\CryptoPayments\Domain\ValueObjects\CryptoAddress;
use App\CryptoPayments\Domain\ValueObjects\Memo;
use App\CryptoPayments\Domain\ValueObjects\NativeCryptoAmount;
use App\CryptoPayments\Domain\ValueObjects\TransactionResult;
use App\CryptoPayments\Domain\ValueObjects\TxHash;
use DateTimeImmutable;

interface BlockchainClientInterface
{
    public function network(): string;

    /** @return CryptoAsset[] */
    public function supportedAssets(): array;

    public function depositMode(): DepositMode;

    // ── Memo-based (TON, USDT_TON) ──

    public function masterDepositAddress(): CryptoAddress;

    // ── UniqueAddress-based (BTC, TRX, USDT_TRC20) ──

    /** @return string[] pre-configured pool of receiving addresses */
    public function depositAddressPool(): array;

    /** Find confirmed incoming tx for a specific address (UniqueAddress mode). */
    public function findIncomingTransactionByAddress(
        CryptoAddress $address,
        CryptoAsset $asset,
        DateTimeImmutable $since,
    ): ?TransactionResult;

    // ── Memo-based batch polling ──

    /**
     * @param  Memo[]  $memos
     * @return array<string, TransactionResult> key = memo string
     */
    public function findIncomingTransactionsBatch(
        array $memos,
        CryptoAsset $asset,
        DateTimeImmutable $since,
    ): array;

    public function findIncomingTransaction(
        Memo $memo,
        CryptoAsset $asset,
        NativeCryptoAmount $expectedAmount,
        DateTimeImmutable $since,
    ): ?TransactionResult;

    // ── Sending (hot wallet) ──

    /** Returns true when a hot wallet private key / mnemonic is configured. */
    public function canSend(): bool;

    /**
     * Sends crypto from the hot wallet to $to address.
     *
     * @throws \RuntimeException if hot wallet not configured or SDK unavailable.
     */
    public function sendTransfer(
        CryptoAddress $to,
        NativeCryptoAmount $amount,
        CryptoAsset $asset,
        string $comment,
    ): TxHash;
}
