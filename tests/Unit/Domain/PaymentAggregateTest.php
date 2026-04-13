<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use App\Payments\Domain\Aggregates\Payment;
use App\Payments\Domain\Enums\PaymentStatus;
use App\Payments\Domain\Events\PaymentWasCancelled;
use App\Payments\Domain\Events\PaymentWasCreated;
use App\Payments\Domain\Events\PaymentWasRefunded;
use App\Payments\Domain\Events\PaymentWasSucceeded;
use App\Payments\Domain\Exceptions\InvalidPaymentStateException;
use App\Payments\Domain\ValueObjects\ExternalId;
use App\Payments\Domain\ValueObjects\Money;
use App\Payments\Domain\ValueObjects\PaymentId;
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
        $payment->pullDomainEvents(); // сбрасываем созданные события
        $payment->markAsSucceeded($externalId);
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
