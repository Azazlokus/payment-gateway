<?php

declare(strict_types=1);

namespace App\CryptoPayments\Application\ACL;

use App\CryptoPayments\Domain\Events\DepositConfirmed;
use App\CryptoPayments\Domain\Events\DepositExpired;
use App\CryptoPayments\Domain\Events\DepositOverpaid;
use App\Payments\Domain\Contracts\PaymentRepositoryInterface;
use App\Payments\Domain\Exceptions\InvalidPaymentStateException;
use App\Payments\Domain\ValueObjects\ExternalId;
use App\Payments\Domain\ValueObjects\PaymentId;
use App\Payments\Infrastructure\Observability\MetricsService;
use App\Payments\Infrastructure\Observability\PaymentLogger;

final class CryptoDepositToPaymentAdapter
{
    public function __construct(
        private readonly PaymentRepositoryInterface $payments,
        private readonly MetricsService $metrics,
        private readonly PaymentLogger $logger,
    ) {}

    public function onDepositConfirmed(DepositConfirmed $event): void
    {
        $payment = $this->payments->findById(PaymentId::fromString($event->paymentId));

        if ($payment === null) {
            $this->logger->warning('Payment not found for confirmed deposit', [
                'payment_id' => $event->paymentId,
                'deposit_id' => $event->depositId,
            ]);

            return;
        }

        if ($payment->status()->isTerminal()) {
            $this->logger->info('Payment already in terminal status, skipping confirm', [
                'payment_id' => $event->paymentId,
                'status'     => $payment->status()->value,
            ]);

            return;
        }

        try {
            $payment->markAsSucceeded(ExternalId::fromString($event->txHash));
            $this->payments->save($payment);
            $this->metrics->paymentSucceeded('crypto_ton');
        } catch (InvalidPaymentStateException $e) {
            $this->logger->warning('Could not mark payment as succeeded', [
                'payment_id' => $event->paymentId,
                'error'      => $e->getMessage(),
            ]);
        }
    }

    public function onDepositExpired(DepositExpired $event): void
    {
        $payment = $this->payments->findById(PaymentId::fromString($event->paymentId));

        if ($payment === null) {
            $this->logger->warning('Payment not found for expired deposit', [
                'payment_id' => $event->paymentId,
                'deposit_id' => $event->depositId,
            ]);

            return;
        }

        if ($payment->status()->isTerminal()) {
            return;
        }

        try {
            $payment->cancel('Crypto deposit expired');
            $this->payments->save($payment);
            $this->metrics->paymentCancelled('crypto_ton');
        } catch (InvalidPaymentStateException $e) {
            $this->logger->warning('Could not cancel payment for expired deposit', [
                'payment_id' => $event->paymentId,
                'error'      => $e->getMessage(),
            ]);
        }
    }

    public function onDepositOverpaid(DepositOverpaid $event): void
    {
        $payment = $this->payments->findById(PaymentId::fromString($event->paymentId));

        if ($payment === null) {
            $this->logger->warning('Payment not found for overpaid deposit', [
                'payment_id' => $event->paymentId,
                'deposit_id' => $event->depositId,
            ]);

            return;
        }

        if ($payment->status()->isTerminal()) {
            $this->logger->info('Payment already in terminal status, skipping overpaid handling', [
                'payment_id' => $event->paymentId,
                'status'     => $payment->status()->value,
            ]);

            return;
        }

        try {
            // Treat overpaid as success — handle excess manually
            $payment->markAsSucceeded(ExternalId::fromString($event->depositId));
            $this->payments->save($payment);
            $this->metrics->paymentSucceeded('crypto_ton');

            $this->logger->warning('Crypto deposit overpaid — manual reconciliation required', [
                'payment_id'    => $event->paymentId,
                'deposit_id'    => $event->depositId,
                'expected_units' => $event->expectedUnits,
                'actual_units'  => $event->actualUnits,
            ]);
        } catch (InvalidPaymentStateException $e) {
            $this->logger->warning('Could not mark overpaid payment as succeeded', [
                'payment_id' => $event->paymentId,
                'error'      => $e->getMessage(),
            ]);
        }
    }
}
