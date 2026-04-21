<?php

declare(strict_types=1);

namespace Tests\Unit\CryptoPayments;

use App\CryptoPayments\Domain\Aggregates\CryptoDeposit;
use App\CryptoPayments\Domain\Enums\CryptoAsset;
use App\CryptoPayments\Domain\Enums\CryptoDepositStatus;
use App\CryptoPayments\Domain\Events\DepositAwaitingPayment;
use App\CryptoPayments\Domain\Events\DepositConfirmed;
use App\CryptoPayments\Domain\Events\DepositExpired;
use App\CryptoPayments\Domain\Events\DepositOverpaid;
use App\CryptoPayments\Domain\Exceptions\DepositExpiredException;
use App\CryptoPayments\Domain\ValueObjects\CryptoDepositId;
use App\CryptoPayments\Domain\ValueObjects\Memo;
use App\CryptoPayments\Domain\ValueObjects\NativeCryptoAmount;
use App\CryptoPayments\Domain\ValueObjects\TonAddress;
use App\CryptoPayments\Domain\ValueObjects\TxHash;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class CryptoDepositAggregateTest extends TestCase
{
    private const ADDRESS = 'UQAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAFy';

    private function makeDeposit(
        int $expectedNanotons = 125_000_000,
        ?DateTimeImmutable $expiresAt = null,
    ): CryptoDeposit {
        return CryptoDeposit::create(
            id: CryptoDepositId::generate(),
            paymentId: 'pay-test-001',
            asset: CryptoAsset::TON,
            expectedAmount: NativeCryptoAmount::ofNanotons($expectedNanotons),
            fiatAmountKopecks: 5000,
            depositAddress: TonAddress::fromString(self::ADDRESS),
            memo: Memo::generate(),
            expiresAt: $expiresAt ?? new DateTimeImmutable('+20 minutes'),
        );
    }

    public function test_create_records_awaiting_event(): void
    {
        $deposit = $this->makeDeposit();

        $events = $deposit->pullDomainEvents();

        $this->assertCount(1, $events);
        $this->assertInstanceOf(DepositAwaitingPayment::class, $events[0]);
        $this->assertSame(CryptoDepositStatus::Awaiting, $deposit->status());
    }

    public function test_pull_domain_events_clears_the_list(): void
    {
        $deposit = $this->makeDeposit();
        $deposit->pullDomainEvents();

        $this->assertEmpty($deposit->pullDomainEvents());
    }

    public function test_confirm_sets_confirmed_status(): void
    {
        $deposit = $this->makeDeposit();
        $deposit->pullDomainEvents();

        $hash   = TxHash::fromString('abc123def456abc123def456abc123def456abc123def456abc123def456abc1');
        $actual = NativeCryptoAmount::ofNanotons(125_000_000);

        $deposit->confirm($hash, $actual);

        $this->assertSame(CryptoDepositStatus::Confirmed, $deposit->status());
        $this->assertSame($hash->toString(), $deposit->txHash()?->toString());
    }

    public function test_confirm_records_deposit_confirmed_event(): void
    {
        $deposit = $this->makeDeposit();
        $deposit->pullDomainEvents();

        $hash = TxHash::fromString('abc123def456abc123def456abc123def456abc123def456abc123def456abc1');
        $deposit->confirm($hash, NativeCryptoAmount::ofNanotons(125_000_000));

        $events = $deposit->pullDomainEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(DepositConfirmed::class, $events[0]);
    }

    public function test_confirm_is_idempotent(): void
    {
        $deposit = $this->makeDeposit();
        $deposit->pullDomainEvents();

        $hash = TxHash::fromString('abc123def456abc123def456abc123def456abc123def456abc123def456abc1');
        $deposit->confirm($hash, NativeCryptoAmount::ofNanotons(125_000_000));
        $deposit->pullDomainEvents();

        // Second confirm should be no-op
        $deposit->confirm($hash, NativeCryptoAmount::ofNanotons(125_000_000));
        $this->assertEmpty($deposit->pullDomainEvents());
    }

    public function test_detect_transaction_overpaid_records_overpaid_event(): void
    {
        $deposit = $this->makeDeposit(expectedNanotons: 100_000_000);
        $deposit->pullDomainEvents();

        $hash = TxHash::fromString('abc123def456abc123def456abc123def456abc123def456abc123def456abc1');
        $deposit->detectTransaction($hash, NativeCryptoAmount::ofNanotons(200_000_000));

        $events = $deposit->pullDomainEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(DepositOverpaid::class, $events[0]);
        $this->assertSame(CryptoDepositStatus::Overpaid, $deposit->status());
    }

    public function test_expire_sets_expired_status(): void
    {
        $deposit = $this->makeDeposit();
        $deposit->pullDomainEvents();

        $deposit->expire();

        $this->assertSame(CryptoDepositStatus::Expired, $deposit->status());
    }

    public function test_expire_records_deposit_expired_event(): void
    {
        $deposit = $this->makeDeposit();
        $deposit->pullDomainEvents();

        $deposit->expire();

        $events = $deposit->pullDomainEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(DepositExpired::class, $events[0]);
    }

    public function test_expire_is_idempotent_for_terminal_status(): void
    {
        $deposit = $this->makeDeposit();
        $deposit->pullDomainEvents();
        $deposit->expire();
        $deposit->pullDomainEvents();

        // Should be no-op since already expired
        $deposit->expire();
        $this->assertEmpty($deposit->pullDomainEvents());
    }

    public function test_confirm_throws_on_expired_deposit(): void
    {
        $deposit = $this->makeDeposit(expiresAt: new DateTimeImmutable('-1 minute'));
        $deposit->pullDomainEvents();

        $this->expectException(DepositExpiredException::class);

        $hash = TxHash::fromString('abc123def456abc123def456abc123def456abc123def456abc123def456abc1');
        $deposit->confirm($hash, NativeCryptoAmount::ofNanotons(125_000_000));
    }

    public function test_restore_does_not_record_events(): void
    {
        $id = CryptoDepositId::generate();
        $deposit = CryptoDeposit::restore(
            id: $id,
            paymentId: 'pay-restored',
            status: CryptoDepositStatus::Awaiting,
            asset: CryptoAsset::TON,
            expectedAmount: NativeCryptoAmount::ofNanotons(100_000_000),
            fiatAmountKopecks: 4000,
            depositAddress: TonAddress::fromString(self::ADDRESS),
            memo: Memo::generate(),
            expiresAt: new DateTimeImmutable('+10 minutes'),
            createdAtTimestamp: time(),
        );

        $this->assertEmpty($deposit->pullDomainEvents());
        $this->assertSame($id->toString(), $deposit->id()->toString());
    }

    public function test_created_at_returns_datetime_from_timestamp(): void
    {
        $deposit = $this->makeDeposit();

        $ts = $deposit->createdAtTimestamp();
        $createdAt = $deposit->createdAt();

        $this->assertSame($ts, $createdAt->getTimestamp());
    }
}
