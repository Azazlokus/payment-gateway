<?php

declare(strict_types=1);

namespace App\Payments\Application\Commands\CapturePayment;

use App\Payments\Application\DTOs\PaymentResultDTO;
use App\Payments\Application\PaymentProviderRegistry;
use App\Payments\Domain\Contracts\PaymentRepositoryInterface;
use App\Payments\Domain\Contracts\SupportsTwoPhasePayments;
use App\Payments\Domain\Enums\PaymentStatus;
use App\Payments\Domain\Exceptions\PaymentException;
use App\Payments\Domain\ValueObjects\Money;
use App\Payments\Domain\ValueObjects\PaymentId;
use App\Payments\Infrastructure\Observability\MetricsService;
use App\Payments\Infrastructure\Observability\NotificationService;
use App\Payments\Infrastructure\Observability\PaymentLogger;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

final readonly class CapturePaymentHandler
{
    public function __construct(
        private PaymentRepositoryInterface $repository,
        private PaymentProviderRegistry $registry,
        private PaymentLogger $logger,
        private MetricsService $metrics,
        private NotificationService $notifications,
    ) {}

    public function handle(CapturePaymentCommand $command): PaymentResultDTO
    {
        $result = DB::transaction(function () use ($command): PaymentResultDTO {
            $payment = $this->repository->findById(PaymentId::fromString($command->paymentId));

            if ($payment === null) {
                throw new PaymentException("Payment not found: {$command->paymentId}", Response::HTTP_NOT_FOUND);
            }

            if ($payment->status() !== PaymentStatus::Authorized) {
                throw new PaymentException(
                    "Payment must be in Authorized status to capture, current: {$payment->status()->value}",
                    Response::HTTP_CONFLICT,
                );
            }

            $provider = $this->registry->resolve($payment->provider());

            if (! $provider instanceof SupportsTwoPhasePayments) {
                throw new PaymentException(
                    "Provider {$payment->provider()} does not support capture",
                    Response::HTTP_UNPROCESSABLE_ENTITY,
                );
            }

            $captureAmount = $command->amountKopecks !== null
                ? Money::ofRub($command->amountKopecks)
                : $payment->amount();

            $provider->capturePayment($payment->externalId(), $captureAmount);
            $payment->capture($captureAmount);
            $this->repository->save($payment);

            activity()
                ->withProperties([
                    'payment_id' => $command->paymentId,
                    'captured_amount' => $captureAmount->formatted(),
                ])
                ->log('payment.captured');

            $this->metrics->paymentSucceeded($payment->provider());
            $this->logger->info('Payment captured', [
                'payment_id' => $command->paymentId,
                'captured_amount' => $captureAmount->formatted(),
                'provider' => $payment->provider(),
            ]);

            return PaymentResultDTO::fromAggregate($payment);
        });

        $payment = $this->repository->findById(PaymentId::fromString($command->paymentId));
        if ($payment !== null) {
            $this->notifications->notify($result, $payment->metadata());
        }

        return $result;
    }
}
