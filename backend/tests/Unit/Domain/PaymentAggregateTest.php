<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use App\Contexts\Payments\Domain\Aggregates\Payment;
use App\Contexts\Payments\Domain\Enums\PaymentStatus;
use App\Contexts\Payments\Domain\Events\PaymentWasAuthorized;
use App\Contexts\Payments\Domain\Events\PaymentWasCancelled;
use App\Contexts\Payments\Domain\Events\PaymentWasCaptured;
use App\Contexts\Payments\Domain\Events\PaymentWasCreated;
use App\Contexts\Payments\Domain\Events\PaymentWasRefunded;
use App\Contexts\Payments\Domain\Events\PaymentWasSucceeded;
use App\Contexts\Payments\Domain\Exceptions\InvalidPaymentStateException;
use App\Contexts\Payments\Domain\Exceptions\PaymentException;
use App\Contexts\Payments\Domain\ValueObjects\ExternalId;
use App\Contexts\Payments\Domain\ValueObjects\Money;
use App\Contexts\Payments\Domain\ValueObjects\PaymentId;
use App\Contexts\Payments\Domain\ValueObjects\SplitRule;
use PHPUnit\Framework\TestCase;

class PaymentAggregateTest extends TestCase  // Pure PHPUnit — no Laravel bootstrap needed
{
    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function makePayment(int $kopecks = 10_000): Payment
    {
        return Payment::create(
            id: PaymentId::generate(),
            amount: Money::ofRub($kopecks),
            description: 'Test payment',
            provider: 'yookassa',
            idempotencyKey: 'idem-'.uniqid(),
        );
    }

    private function makeSucceededPayment(int $kopecks = 10_000): Payment
    {
        $payment = $this->makePayment($kopecks);
        $externalId = ExternalId::fromString('22d65900-000f-5000-a000-10d000000099');
        $payment->pullDomainEvents();
        $payment->markAsSucceeded($externalId);
        $payment->pullDomainEvents();

        return $payment;
    }

    private function makeAuthorizedPayment(int $kopecks = 10_000): Payment
    {
        $payment = $this->makePayment($kopecks);
        $externalId = ExternalId::fromString('22d65900-000f-5000-a000-10d000000099');
        $payment->pullDomainEvents();
        $payment->authorize($externalId);
        $payment->pullDomainEvents();

        return $payment;
    }

    // ─── create() ─────────────────────────────────────────────────────────────

    public function test_create_sets_pending_status(): void
    {
        $payment = $this->makePayment();

        $this->assertSame(PaymentStatus::Pending, $payment->status());
    }

    public function test_create_records_payment_was_created_event(): void
    {
        $payment = $this->makePayment(5_000);
        $events = $payment->pullDomainEvents();

        $this->assertCount(1, $events);
        $this->assertInstanceOf(PaymentWasCreated::class, $events[0]);
        $this->assertSame(5_000, $events[0]->amount);
        $this->assertSame('yookassa', $events[0]->provider);
    }

    public function test_pull_domain_events_clears_the_list(): void
    {
        $payment = $this->makePayment();
        $payment->pullDomainEvents();

        $this->assertEmpty($payment->pullDomainEvents());
    }

    // ─── markAsSucceeded() ────────────────────────────────────────────────────

    public function test_mark_as_succeeded_transitions_to_succeeded(): void
    {
        $payment = $this->makePayment();
        $externalId = ExternalId::fromString('22d65900-000f-5000-a000-10d000000099');
        $payment->pullDomainEvents();

        $payment->markAsSucceeded($externalId);

        $this->assertSame(PaymentStatus::Succeeded, $payment->status());
        $this->assertTrue($externalId->equals($payment->externalId()));
    }

    public function test_mark_as_succeeded_records_event(): void
    {
        $payment = $this->makePayment();
        $externalId = ExternalId::fromString('22d65900-000f-5000-a000-10d000000099');
        $payment->pullDomainEvents();

        $payment->markAsSucceeded($externalId);
        $events = $payment->pullDomainEvents();

        $this->assertCount(1, $events);
        $this->assertInstanceOf(PaymentWasSucceeded::class, $events[0]);
    }

    public function test_mark_as_succeeded_throws_when_already_terminal(): void
    {
        $payment = $this->makePayment();
        $externalId = ExternalId::fromString('22d65900-000f-5000-a000-10d000000099');
        $payment->markAsSucceeded($externalId);

        $this->expectException(InvalidPaymentStateException::class);

        $payment->markAsSucceeded($externalId);
    }

    // ─── cancel() ─────────────────────────────────────────────────────────────

    public function test_cancel_transitions_to_cancelled(): void
    {
        $payment = $this->makePayment();
        $payment->pullDomainEvents();

        $payment->cancel('User request');

        $this->assertSame(PaymentStatus::Cancelled, $payment->status());
    }

    public function test_cancel_records_event_with_reason(): void
    {
        $payment = $this->makePayment();
        $payment->pullDomainEvents();

        $payment->cancel('Expired');
        $events = $payment->pullDomainEvents();

        $this->assertCount(1, $events);
        $this->assertInstanceOf(PaymentWasCancelled::class, $events[0]);
        $this->assertSame('Expired', $events[0]->reason);
    }

    public function test_cancel_throws_when_already_terminal(): void
    {
        $payment = $this->makePayment();
        $payment->cancel('First');

        $this->expectException(InvalidPaymentStateException::class);

        $payment->cancel('Second');
    }

    // ─── refund() ─────────────────────────────────────────────────────────────

    public function test_full_refund_transitions_to_refunded(): void
    {
        $payment = $this->makeSucceededPayment(10_000);

        $payment->refund(Money::ofRub(10_000));

        $this->assertSame(PaymentStatus::Refunded, $payment->status());
        $this->assertSame(10_000, $payment->refundedAmountKopecks());
    }

    public function test_partial_refund_keeps_succeeded_status(): void
    {
        $payment = $this->makeSucceededPayment(10_000);

        $payment->refund(Money::ofRub(4_000));

        $this->assertSame(PaymentStatus::Succeeded, $payment->status());
        $this->assertSame(4_000, $payment->refundedAmountKopecks());
    }

    public function test_multiple_partial_refunds_up_to_full_amount(): void
    {
        $payment = $this->makeSucceededPayment(10_000);

        $payment->refund(Money::ofRub(4_000));
        $payment->refund(Money::ofRub(3_000));
        $payment->refund(Money::ofRub(3_000));

        $this->assertSame(PaymentStatus::Refunded, $payment->status());
        $this->assertSame(10_000, $payment->refundedAmountKopecks());
    }

    public function test_refund_records_payment_was_refunded_event(): void
    {
        $payment = $this->makeSucceededPayment(10_000);

        $payment->refund(Money::ofRub(5_000));
        $events = $payment->pullDomainEvents();

        $this->assertCount(1, $events);
        $this->assertInstanceOf(PaymentWasRefunded::class, $events[0]);
        $this->assertSame(5_000, $events[0]->refundAmount);
    }

    public function test_refund_throws_when_not_succeeded(): void
    {
        $payment = $this->makePayment();

        $this->expectException(InvalidPaymentStateException::class);
        $this->expectExceptionMessageMatches('/Cannot refund payment in status/');

        $payment->refund(Money::ofRub(5_000));
    }

    public function test_refund_throws_when_amount_exceeds_payment(): void
    {
        $payment = $this->makeSucceededPayment(10_000);

        $this->expectException(InvalidPaymentStateException::class);
        $this->expectExceptionMessageMatches('/exceed/i');

        $payment->refund(Money::ofRub(10_001));
    }

    public function test_refund_throws_when_cumulative_exceeds_payment(): void
    {
        $payment = $this->makeSucceededPayment(10_000);
        $payment->refund(Money::ofRub(6_000));

        $this->expectException(InvalidPaymentStateException::class);

        $payment->refund(Money::ofRub(5_000)); // 6000 + 5000 = 11000 > 10000
    }

    public function test_refund_throws_after_full_refund(): void
    {
        $payment = $this->makeSucceededPayment(10_000);
        $payment->refund(Money::ofRub(10_000));

        $this->expectException(InvalidPaymentStateException::class);
        $this->expectExceptionMessageMatches('/Cannot refund payment in status/');

        $payment->refund(Money::ofRub(100));
    }

    // ─── authorize() ──────────────────────────────────────────────────────────

    public function test_authorize_transitions_to_authorized(): void
    {
        $payment = $this->makePayment();
        $externalId = ExternalId::fromString('22d65900-000f-5000-a000-10d000000099');
        $payment->pullDomainEvents();

        $payment->authorize($externalId);

        $this->assertSame(PaymentStatus::Authorized, $payment->status());
        $this->assertTrue($externalId->equals($payment->externalId()));
    }

    public function test_authorize_records_event(): void
    {
        $payment = $this->makePayment();
        $externalId = ExternalId::fromString('22d65900-000f-5000-a000-10d000000099');
        $payment->pullDomainEvents();

        $payment->authorize($externalId);
        $events = $payment->pullDomainEvents();

        $this->assertCount(1, $events);
        $this->assertInstanceOf(PaymentWasAuthorized::class, $events[0]);
    }

    public function test_authorize_throws_when_not_pending(): void
    {
        $payment = $this->makeSucceededPayment();

        $this->expectException(InvalidPaymentStateException::class);

        $payment->authorize(ExternalId::fromString('22d65900-000f-5000-a000-10d000000099'));
    }

    // ─── capture() ──────────────────────────────────────────────────────────

    public function test_capture_full_amount_transitions_to_succeeded(): void
    {
        $payment = $this->makeAuthorizedPayment(10_000);

        $payment->capture(Money::ofRub(10_000));

        $this->assertSame(PaymentStatus::Succeeded, $payment->status());
        $this->assertSame(10_000, $payment->capturedAmountKopecks());
    }

    public function test_capture_without_amount_captures_full(): void
    {
        $payment = $this->makeAuthorizedPayment(10_000);

        $payment->capture();

        $this->assertSame(PaymentStatus::Succeeded, $payment->status());
        $this->assertSame(10_000, $payment->capturedAmountKopecks());
    }

    public function test_capture_partial_amount(): void
    {
        $payment = $this->makeAuthorizedPayment(10_000);

        $payment->capture(Money::ofRub(7_000));

        $this->assertSame(PaymentStatus::Succeeded, $payment->status());
        $this->assertSame(7_000, $payment->capturedAmountKopecks());
    }

    public function test_capture_records_event(): void
    {
        $payment = $this->makeAuthorizedPayment(10_000);

        $payment->capture(Money::ofRub(10_000));
        $events = $payment->pullDomainEvents();

        $this->assertCount(1, $events);
        $this->assertInstanceOf(PaymentWasCaptured::class, $events[0]);
        $this->assertSame(10_000, $events[0]->capturedAmountKopecks);
    }

    public function test_capture_throws_when_not_authorized(): void
    {
        $payment = $this->makePayment();

        $this->expectException(InvalidPaymentStateException::class);

        $payment->capture(Money::ofRub(10_000));
    }

    public function test_capture_throws_when_amount_exceeds_authorized(): void
    {
        $payment = $this->makeAuthorizedPayment(10_000);

        $this->expectException(InvalidPaymentStateException::class);

        $payment->capture(Money::ofRub(10_001));
    }

    public function test_cancel_authorized_payment_voids_hold(): void
    {
        $payment = $this->makeAuthorizedPayment(10_000);

        $payment->cancel('Void hold');

        $this->assertSame(PaymentStatus::Cancelled, $payment->status());
    }

    // ─── refund after partial capture ───────────────────────────────────────

    public function test_refund_after_partial_capture_limited_to_captured_amount(): void
    {
        $payment = $this->makeAuthorizedPayment(10_000);
        $payment->capture(Money::ofRub(7_000));
        $payment->pullDomainEvents();

        $payment->refund(Money::ofRub(7_000));

        $this->assertSame(PaymentStatus::Refunded, $payment->status());
    }

    public function test_refund_after_partial_capture_throws_when_exceeds_captured(): void
    {
        $payment = $this->makeAuthorizedPayment(10_000);
        $payment->capture(Money::ofRub(7_000));
        $payment->pullDomainEvents();

        $this->expectException(InvalidPaymentStateException::class);

        $payment->refund(Money::ofRub(7_001));
    }

    // ─── splits ─────────────────────────────────────────────────────────────

    public function test_create_with_splits(): void
    {
        $splits = [
            new SplitRule('acc_seller', Money::ofRub(7_000), 'Seller share'),
            new SplitRule('acc_platform', Money::ofRub(3_000), 'Platform fee'),
        ];

        $payment = Payment::create(
            id: PaymentId::generate(),
            amount: Money::ofRub(10_000),
            description: 'Split test',
            provider: 'yookassa',
            idempotencyKey: 'idem-split-1',
            splits: $splits,
        );

        $this->assertTrue($payment->hasSplits());
        $this->assertCount(2, $payment->splits());
        $this->assertSame(10_000, $payment->splitsTotal());
    }

    public function test_splits_total_less_than_amount_is_allowed(): void
    {
        $splits = [
            new SplitRule('acc_seller', Money::ofRub(6_000), 'Seller'),
        ];

        $payment = Payment::create(
            id: PaymentId::generate(),
            amount: Money::ofRub(10_000),
            description: 'Partial split',
            provider: 'yookassa',
            idempotencyKey: 'idem-split-2',
            splits: $splits,
        );

        $this->assertTrue($payment->hasSplits());
        $this->assertSame(6_000, $payment->splitsTotal());
    }

    public function test_splits_total_exceeds_amount_throws(): void
    {
        $splits = [
            new SplitRule('acc_a', Money::ofRub(6_000)),
            new SplitRule('acc_b', Money::ofRub(5_000)),
        ];

        $this->expectException(InvalidPaymentStateException::class);
        $this->expectExceptionMessage('exceeds payment amount');

        Payment::create(
            id: PaymentId::generate(),
            amount: Money::ofRub(10_000),
            description: 'Over-split',
            provider: 'yookassa',
            idempotencyKey: 'idem-split-3',
            splits: $splits,
        );
    }

    public function test_payment_without_splits_has_empty_array(): void
    {
        $payment = $this->makePayment();

        $this->assertFalse($payment->hasSplits());
        $this->assertSame([], $payment->splits());
        $this->assertSame(0, $payment->splitsTotal());
    }

    public function test_split_rule_empty_account_throws(): void
    {
        $this->expectException(PaymentException::class);

        new SplitRule('', Money::ofRub(1_000));
    }

    // ─── assignExternalData() ────────────────────────────────────────────────

    public function test_assign_external_data_stores_values(): void
    {
        $payment = $this->makePayment();
        $externalId = ExternalId::fromString('22d65900-000f-5000-a000-10d000000099');

        $payment->assignExternalData($externalId, 'https://pay.example.com', 'pm_abc');

        $this->assertTrue($externalId->equals($payment->externalId()));
        $this->assertSame('https://pay.example.com', $payment->confirmationUrl());
        $this->assertSame('pm_abc', $payment->paymentMethodId());
    }

    // ─── restore() ───────────────────────────────────────────────────────────

    public function test_restore_sets_refunded_amount(): void
    {
        $payment = Payment::restore(
            id: PaymentId::generate(),
            amount: Money::ofRub(10_000),
            status: PaymentStatus::Succeeded,
            description: 'Restored payment',
            provider: 'yookassa',
            idempotencyKey: 'key-restore',
            refundedAmountKopecks: 3_000,
        );

        $this->assertSame(3_000, $payment->refundedAmountKopecks());
        $this->assertSame(PaymentStatus::Succeeded, $payment->status());
    }

    public function test_restore_does_not_record_events(): void
    {
        $payment = Payment::restore(
            id: PaymentId::generate(),
            amount: Money::ofRub(10_000),
            status: PaymentStatus::Pending,
            description: 'Restored',
            provider: 'yookassa',
            idempotencyKey: 'key-restore-2',
        );

        $this->assertEmpty($payment->pullDomainEvents());
    }
}
