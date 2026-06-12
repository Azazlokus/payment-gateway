<?php

declare(strict_types=1);

namespace Tests\Unit\CryptoPayments;

use App\Contexts\CryptoPayments\Domain\Aggregates\CryptoRefundRequest;
use App\Contexts\CryptoPayments\Domain\Enums\CryptoAsset;
use App\Contexts\CryptoPayments\Domain\Enums\CryptoRefundStatus;
use App\Contexts\CryptoPayments\Domain\Events\RefundHasFailed;
use App\Contexts\CryptoPayments\Domain\Events\RefundWasCompleted;
use App\Contexts\CryptoPayments\Domain\Events\RefundWasRequested;
use App\Contexts\CryptoPayments\Domain\ValueObjects\CryptoAddress;
use App\Contexts\CryptoPayments\Domain\ValueObjects\NativeCryptoAmount;
use App\Contexts\CryptoPayments\Domain\ValueObjects\TxHash;
use PHPUnit\Framework\TestCase;

class CryptoRefundAggregateTest extends TestCase
{
    private const ADDRESS = 'UQAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAFy';

    private const DEPOSIT_ID = '01HXXXXXXXXXXXXXXXXXXXXXXXX';

    private function makeRefund(): CryptoRefundRequest
    {
        return CryptoRefundRequest::create(
            depositId: self::DEPOSIT_ID,
            toAddress: CryptoAddress::fromString(self::ADDRESS),
            amount: NativeCryptoAmount::ofNanotons(125_000_000),
            asset: CryptoAsset::TON,
        );
    }

    public function test_create_sets_pending_status(): void
    {
        $refund = $this->makeRefund();

        $this->assertSame(CryptoRefundStatus::Pending, $refund->status());
    }

    public function test_create_emits_requested_event(): void
    {
        $refund = $this->makeRefund();
        $events = $refund->pullDomainEvents();

        $this->assertCount(1, $events);
        $this->assertInstanceOf(RefundWasRequested::class, $events[0]);

        /** @var RefundWasRequested $event */
        $event = $events[0];
        $this->assertSame(self::DEPOSIT_ID, $event->depositId);
        $this->assertSame(self::ADDRESS, $event->toAddress);
        $this->assertSame(125_000_000, $event->amountUnits);
        $this->assertSame('TON', $event->asset);
    }

    public function test_pull_events_clears_queue(): void
    {
        $refund = $this->makeRefund();
        $refund->pullDomainEvents();

        $this->assertEmpty($refund->pullDomainEvents());
    }

    public function test_mark_as_broadcasting_transitions_status(): void
    {
        $refund = $this->makeRefund();
        $refund->pullDomainEvents();

        $refund->markAsBroadcasting();

        $this->assertSame(CryptoRefundStatus::Broadcasting, $refund->status());
        $this->assertEmpty($refund->pullDomainEvents()); // no event for broadcasting
    }

    public function test_mark_as_broadcasting_throws_if_not_pending(): void
    {
        $refund = $this->makeRefund();
        $refund->pullDomainEvents();
        $refund->markAsBroadcasting();

        $this->expectException(\LogicException::class);
        $refund->markAsBroadcasting();
    }

    public function test_mark_as_completed_stores_hash_and_emits_event(): void
    {
        $hash = TxHash::fromString(str_repeat('a', 64));
        $refund = $this->makeRefund();
        $refund->pullDomainEvents();
        $refund->markAsBroadcasting();

        $refund->markAsCompleted($hash);

        $this->assertSame(CryptoRefundStatus::Completed, $refund->status());
        $this->assertSame(str_repeat('a', 64), $refund->txHash()?->toString());

        $events = $refund->pullDomainEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(RefundWasCompleted::class, $events[0]);
    }

    public function test_mark_as_failed_stores_reason_and_emits_event(): void
    {
        $refund = $this->makeRefund();
        $refund->pullDomainEvents();

        $refund->markAsFailed('Hot wallet not configured');

        $this->assertSame(CryptoRefundStatus::Failed, $refund->status());
        $this->assertSame('Hot wallet not configured', $refund->failureReason());

        $events = $refund->pullDomainEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(RefundHasFailed::class, $events[0]);
    }

    public function test_restore_hydrates_aggregate(): void
    {
        $refund = CryptoRefundRequest::restore(
            id: '01HREFUND00000000000000000',
            depositId: self::DEPOSIT_ID,
            toAddress: self::ADDRESS,
            amountUnits: 250_000_000,
            asset: 'TON',
            status: 'completed',
            txHash: str_repeat('b', 64),
            failureReason: null,
        );

        $this->assertSame(CryptoRefundStatus::Completed, $refund->status());
        $this->assertSame(250_000_000, $refund->amount()->units());
        $this->assertSame(str_repeat('b', 64), $refund->txHash()?->toString());
        $this->assertNull($refund->failureReason());
        $this->assertEmpty($refund->pullDomainEvents()); // restore produces no events
    }

    public function test_completed_status_is_terminal(): void
    {
        $this->assertTrue(CryptoRefundStatus::Completed->isTerminal());
        $this->assertTrue(CryptoRefundStatus::Failed->isTerminal());
        $this->assertFalse(CryptoRefundStatus::Pending->isTerminal());
        $this->assertFalse(CryptoRefundStatus::Broadcasting->isTerminal());
    }
}
