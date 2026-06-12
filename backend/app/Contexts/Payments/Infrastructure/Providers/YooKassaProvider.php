<?php

declare(strict_types=1);

namespace App\Contexts\Payments\Infrastructure\Providers;

use App\Contexts\Payments\Application\DTOs\CreatePaymentOptionsDTO;
use App\Contexts\Payments\Application\DTOs\ReceiptDTO;
use App\Contexts\Payments\Application\DTOs\ReceiptItemDTO;
use App\Contexts\Payments\Domain\Contracts\PaymentProviderInterface;
use App\Contexts\Payments\Domain\Contracts\ProviderResponse;
use App\Contexts\Payments\Domain\Contracts\SupportsSplitPayments;
use App\Contexts\Payments\Domain\Contracts\SupportsTokenization;
use App\Contexts\Payments\Domain\Contracts\SupportsTwoPhasePayments;
use App\Contexts\Payments\Domain\Contracts\TokenizationResult;
use App\Contexts\Payments\Domain\Exceptions\PaymentException;
use App\Contexts\Payments\Domain\ValueObjects\ExternalId;
use App\Contexts\Payments\Domain\ValueObjects\Money;
use App\Contexts\Payments\Domain\ValueObjects\SplitRule;
use App\Contexts\Payments\Infrastructure\Observability\PaymentLogger;
use Illuminate\Support\Str;
use YooKassa\Client;
use YooKassa\Model\Payment\PaymentMethod\AbstractPaymentMethod;
use YooKassa\Model\Payment\PaymentMethod\BankCard;
use YooKassa\Model\Payment\PaymentMethod\PaymentMethodBankCard;
use YooKassa\Request\Payments\CancelResponse;
use YooKassa\Request\Payments\CreateCaptureResponse;
use YooKassa\Request\Payments\CreatePaymentResponse;
use YooKassa\Request\Refunds\CreateRefundResponse;

final readonly class YooKassaProvider implements PaymentProviderInterface, SupportsSplitPayments, SupportsTokenization, SupportsTwoPhasePayments
{
    private Client $client;

    public function __construct(
        private string $shopId,
        private string $secretKey,
        private PaymentLogger $logger,
        ?Client $client = null,
    ) {
        $this->client = $client ?? new Client;
        $this->client->setAuth((int) $this->shopId, $this->secretKey);
    }

    public function name(): string
    {
        return 'yookassa';
    }

    public function createPayment(
        string $paymentId,
        Money $amount,
        string $description,
        string $returnUrl,
        string $idempotencyKey,
        CreatePaymentOptionsDTO $options = new CreatePaymentOptionsDTO,
    ): ProviderResponse {
        return $this->doCreatePayment($paymentId, $amount, $description, $returnUrl, $idempotencyKey, $options, capture: true);
    }

    public function authorizePayment(
        string $paymentId,
        Money $amount,
        string $description,
        string $returnUrl,
        string $idempotencyKey,
        CreatePaymentOptionsDTO $options = new CreatePaymentOptionsDTO,
    ): ProviderResponse {
        return $this->doCreatePayment($paymentId, $amount, $description, $returnUrl, $idempotencyKey, $options, capture: false);
    }

    /** @param SplitRule[] $splits */
    public function createSplitPayment(
        string $paymentId,
        Money $amount,
        string $description,
        string $returnUrl,
        string $idempotencyKey,
        array $splits,
        CreatePaymentOptionsDTO $options = new CreatePaymentOptionsDTO,
    ): ProviderResponse {
        try {
            $this->logger->info('YooKassa: создание split-платежа', [
                'payment_id' => $paymentId,
                'amount' => $amount->formatted(),
                'splits_count' => count($splits),
            ]);

            $payload = [
                'amount' => [
                    'value' => number_format($amount->amount() / 100, 2, '.', ''),
                    'currency' => $amount->currency()->value,
                ],
                'description' => $description,
                'metadata' => ['internal_payment_id' => $paymentId],
                'capture' => true,
                'transfers' => array_map(fn (SplitRule $split): array => [
                    'account_id' => $split->accountId(),
                    'amount' => [
                        'value' => number_format($split->amount()->amount() / 100, 2, '.', ''),
                        'currency' => $split->amount()->currency()->value,
                    ],
                    'description' => $split->description(),
                ], $splits),
            ];

            if ($options->paymentMethodId !== null) {
                $payload['payment_method_id'] = $options->paymentMethodId;
            } else {
                $payload['confirmation'] = $this->buildConfirmation($options, $returnUrl);

                if ($options->paymentMethodType !== null) {
                    $payload['payment_method_data'] = ['type' => $options->paymentMethodType];
                }
            }

            if ($options->receipt instanceof ReceiptDTO) {
                $payload['receipt'] = $this->buildReceipt($options, $amount);
            }

            $response = retry(
                times: 3,
                callback: fn (): ?CreatePaymentResponse => $this->client->createPayment($payload, $idempotencyKey),
                sleepMilliseconds: 500,
                when: fn (\Throwable $e): bool => $this->isRetryable($e),
            );

            $confirmationUrl = $options->paymentMethodId !== null
                ? ''
                : ($response->getConfirmation()?->getConfirmationUrl() ?? '');

            return new ProviderResponse(
                externalId: ExternalId::fromString($response->getId()),
                confirmationUrl: $confirmationUrl,
                status: $response->getStatus(),
                rawData: method_exists($response, 'jsonSerialize') ? $response->jsonSerialize() : [],
            );
        } catch (\Throwable $e) {
            $this->logger->error('YooKassa: ошибка создания split-платежа', [
                'payment_id' => $paymentId,
                'error' => $e->getMessage(),
            ]);

            throw new PaymentException("YooKassa createSplitPayment failed: {$e->getMessage()}", $e->getCode(), previous: $e);
        }
    }

    public function capturePayment(ExternalId $externalId, Money $amount): ProviderResponse
    {
        try {
            $response = retry(
                times: 3,
                callback: fn (): ?CreateCaptureResponse => $this->client->capturePayment(
                    [
                        'amount' => [
                            'value' => number_format($amount->amount() / 100, 2, '.', ''),
                            'currency' => $amount->currency()->value,
                        ],
                    ],
                    $externalId->toString(),
                    (string) Str::uuid(),
                ),
                sleepMilliseconds: 500,
                when: fn (\Throwable $e): bool => $this->isRetryable($e),
            );

            return new ProviderResponse(
                externalId: ExternalId::fromString($response->getId()),
                confirmationUrl: '',
                status: $response->getStatus(),
                rawData: method_exists($response, 'jsonSerialize') ? $response->jsonSerialize() : [],
            );
        } catch (\Throwable $e) {
            throw new PaymentException("YooKassa capture failed: {$e->getMessage()}", $e->getCode(), previous: $e);
        }
    }

    public function voidPayment(ExternalId $externalId): ProviderResponse
    {
        try {
            $response = retry(
                times: 3,
                callback: fn (): ?CancelResponse => $this->client->cancelPayment($externalId->toString(), (string) Str::uuid()),
                sleepMilliseconds: 500,
                when: fn (\Throwable $e): bool => $this->isRetryable($e),
            );

            return new ProviderResponse(
                externalId: ExternalId::fromString($response->getId()),
                confirmationUrl: '',
                status: $response->getStatus(),
                rawData: method_exists($response, 'jsonSerialize') ? $response->jsonSerialize() : [],
            );
        } catch (\Throwable $e) {
            throw new PaymentException("YooKassa void failed: {$e->getMessage()}", $e->getCode(), previous: $e);
        }
    }

    public function getPayment(ExternalId $externalId): ProviderResponse
    {
        try {
            $response = $this->client->getPaymentInfo($externalId->toString());

            return new ProviderResponse(
                externalId: ExternalId::fromString($response->getId()),
                confirmationUrl: '',
                status: $response->getStatus(),
                paymentMethodId: $response->getPaymentMethod()?->getId(),
                rawData: method_exists($response, 'jsonSerialize') ? $response->jsonSerialize() : [],
            );
        } catch (\Throwable $e) {
            throw new PaymentException("YooKassa getPayment failed: {$e->getMessage()}", $e->getCode(), previous: $e);
        }
    }

    public function refundPayment(ExternalId $externalId, Money $amount): ProviderResponse
    {
        try {
            $response = retry(
                times: 3,
                callback: fn (): ?CreateRefundResponse => $this->client->createRefund(
                    [
                        'payment_id' => $externalId->toString(),
                        'amount' => [
                            'value' => number_format($amount->amount() / 100, 2, '.', ''),
                            'currency' => $amount->currency()->value,
                        ],
                    ],
                    (string) Str::uuid(),
                ),
                sleepMilliseconds: 500,
                when: fn (\Throwable $e): bool => $this->isRetryable($e),
            );

            return new ProviderResponse(
                externalId: ExternalId::fromString($response->getId()),
                confirmationUrl: '',
                status: $response->getStatus(),
                rawData: method_exists($response, 'jsonSerialize') ? $response->jsonSerialize() : [],
            );
        } catch (\Throwable $e) {
            throw new PaymentException("YooKassa refund failed: {$e->getMessage()}", $e->getCode(), previous: $e);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, list<string|null>>  $headers
     */
    public function verifyWebhook(array $payload, array $headers): bool
    {
        $allowedCidrs = config('payments.yookassa.webhook_ips', []);
        $requestIp = request()->ip();

        if (! empty($allowedCidrs) && ! $this->ipInAllowedRanges($requestIp, $allowedCidrs)) {
            $this->logger->warning('YooKassa: webhook с неизвестного IP', ['ip' => $requestIp]);

            return false;
        }

        return isset($payload['event'], $payload['object']['id']);
    }

    /** @param array<string, mixed> $payload */
    public function parseWebhook(array $payload): ProviderResponse
    {
        $object = $payload['object'];
        $event = $payload['event'] ?? '';

        // Для refund.* событий object['id'] — это ID рефанда, а не платежа.
        // Платёж нужно искать по object['payment_id'].
        if (str_starts_with($event, 'refund.')) {
            $kopecks = isset($object['amount']['value'])
                ? (int) round((float) $object['amount']['value'] * 100)
                : null;

            return new ProviderResponse(
                externalId: ExternalId::fromString($object['payment_id']),
                confirmationUrl: '',
                status: $object['status'],
                refundAmountKopecks: $kopecks,
                rawData: $payload,
            );
        }

        return new ProviderResponse(
            externalId: ExternalId::fromString($object['id']),
            confirmationUrl: '',
            status: $object['status'],
            paymentMethodId: $object['payment_method']['id'] ?? null,
            rawData: $payload,
        );
    }

    // ─── SupportsTokenization ──────────────────────────────────────────────

    public function tokenize(string $paymentId): TokenizationResult
    {
        try {
            $response = $this->client->getPaymentInfo($paymentId);
            $method = $response->getPaymentMethod();

            if (! $method instanceof AbstractPaymentMethod || ! $method->getSaved()) {
                throw new PaymentException('Payment method was not saved for this payment');
            }

            $card = $method instanceof PaymentMethodBankCard ? $method->getCard() : null;

            return new TokenizationResult(
                token: $method->getId(),
                type: $method->getType() ?? 'card',
                last4: $card?->getLast4() ?? '0000',
                brand: $card?->getCardType() ?? 'unknown',
                expiresAt: $card instanceof BankCard ? sprintf('%s/%s', $card->getExpiryMonth(), $card->getExpiryYear()) : null,
            );
        } catch (PaymentException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new PaymentException("YooKassa tokenize failed: {$e->getMessage()}", $e->getCode(), previous: $e);
        }
    }

    public function chargeToken(
        string $token,
        Money $amount,
        string $description,
        string $idempotencyKey,
    ): ProviderResponse {
        try {
            $this->logger->info('YooKassa: рекуррентное списание', [
                'token' => substr($token, 0, 8).'...',
                'amount' => $amount->formatted(),
            ]);

            $payload = [
                'amount' => [
                    'value' => number_format($amount->amount() / 100, 2, '.', ''),
                    'currency' => $amount->currency()->value,
                ],
                'description' => $description,
                'capture' => true,
                'payment_method_id' => $token,
            ];

            $response = retry(
                times: 3,
                callback: fn (): ?CreatePaymentResponse => $this->client->createPayment($payload, $idempotencyKey),
                sleepMilliseconds: 500,
                when: fn (\Throwable $e): bool => $this->isRetryable($e),
            );

            return new ProviderResponse(
                externalId: ExternalId::fromString($response->getId()),
                confirmationUrl: '',
                status: $response->getStatus(),
                paymentMethodId: $token,
                rawData: method_exists($response, 'jsonSerialize') ? $response->jsonSerialize() : [],
            );
        } catch (PaymentException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new PaymentException("YooKassa chargeToken failed: {$e->getMessage()}", $e->getCode(), previous: $e);
        }
    }

    public function deleteToken(string $token): void
    {
        // YooKassa does not provide an API to delete saved payment methods.
        // Token simply expires or is not reused.
        $this->logger->info('YooKassa: token deletion requested (no-op)', [
            'token' => substr($token, 0, 8).'...',
        ]);
    }

    // ─── Private helpers ────────────────────────────────────────────────────

    private function doCreatePayment(
        string $paymentId,
        Money $amount,
        string $description,
        string $returnUrl,
        string $idempotencyKey,
        CreatePaymentOptionsDTO $options,
        bool $capture,
    ): ProviderResponse {
        try {
            $this->logger->info('YooKassa: создание платежа', [
                'payment_id' => $paymentId,
                'amount' => $amount->formatted(),
                'idempotency_key' => $idempotencyKey,
                'capture' => $capture,
                'save_method' => $options->savePaymentMethod,
            ]);

            $payload = [
                'amount' => [
                    'value' => number_format($amount->amount() / 100, 2, '.', ''),
                    'currency' => $amount->currency()->value,
                ],
                'description' => $description,
                'metadata' => ['internal_payment_id' => $paymentId],
                'capture' => $capture,
                'save_payment_method' => $options->savePaymentMethod,
            ];

            if ($options->paymentMethodId !== null) {
                $payload['payment_method_id'] = $options->paymentMethodId;
            } else {
                $payload['confirmation'] = $this->buildConfirmation($options, $returnUrl);

                if ($options->paymentMethodType !== null) {
                    $payload['payment_method_data'] = ['type' => $options->paymentMethodType];
                }
            }

            if ($options->receipt instanceof ReceiptDTO) {
                $payload['receipt'] = $this->buildReceipt($options, $amount);
            }

            $response = retry(
                times: 3,
                callback: fn (): ?CreatePaymentResponse => $this->client->createPayment($payload, $idempotencyKey),
                sleepMilliseconds: 500,
                when: fn (\Throwable $e): bool => $this->isRetryable($e),
            );

            $this->logger->info('YooKassa: платёж создан', [
                'payment_id' => $paymentId,
                'external_id' => $response->getId(),
                'status' => $response->getStatus(),
                'capture' => $capture,
            ]);

            $paymentMethodId = null;
            if ($options->savePaymentMethod && $response->getPaymentMethod()?->getSaved()) {
                $paymentMethodId = $response->getPaymentMethod()->getId();
            }

            $confirmationUrl = $options->paymentMethodId !== null
                ? ''
                : ($response->getConfirmation()?->getConfirmationUrl() ?? '');

            return new ProviderResponse(
                externalId: ExternalId::fromString($response->getId()),
                confirmationUrl: $confirmationUrl,
                status: $response->getStatus(),
                paymentMethodId: $paymentMethodId,
                rawData: method_exists($response, 'jsonSerialize') ? $response->jsonSerialize() : [],
            );
        } catch (PaymentException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $this->logger->error('YooKassa: ошибка создания платежа', [
                'payment_id' => $paymentId,
                'error' => $e->getMessage(),
            ]);

            throw new PaymentException("YooKassa createPayment failed: {$e->getMessage()}", $e->getCode(), previous: $e);
        }
    }

    /** @return array<string, string> */
    private function buildConfirmation(CreatePaymentOptionsDTO $options, string $returnUrl): array
    {
        return match ($options->confirmationType) {
            'qr' => ['type' => 'qr'],
            'embedded' => ['type' => 'embedded'],
            'mobile_application' => ['type' => 'mobile_application', 'return_url' => $returnUrl],
            default => ['type' => 'redirect', 'return_url' => $returnUrl],
        };
    }

    /** @return array<string, mixed> */
    private function buildReceipt(CreatePaymentOptionsDTO $options, Money $amount): array
    {
        $receipt = $options->receipt;

        $customer = array_filter([
            'email' => $receipt->email,
            'phone' => $receipt->phone,
        ]);

        $items = array_map(fn (ReceiptItemDTO $item): array => [
            'description' => $item->description,
            'quantity' => $item->quantity,
            'amount' => [
                'value' => number_format($item->amountKopecks / 100, 2, '.', ''),
                'currency' => $amount->currency()->value,
            ],
            'vat_code' => $item->vatCode,
            'payment_subject' => $item->paymentSubject,
            'payment_mode' => $item->paymentMode,
        ], $receipt->items);

        return ['customer' => $customer, 'items' => $items];
    }

    private function isRetryable(\Throwable $e): bool
    {
        // Ретраим только на 5xx ошибки сервера YooKassa
        if ($e->getCode() >= 500) {
            return true;
        }

        return str_contains($e->getMessage(), 'cURL error') ||
               str_contains($e->getMessage(), 'timed out');
    }

    /** @param array<string> $cidrs */
    private function ipInAllowedRanges(string $ip, array $cidrs): bool
    {
        $ipLong = ip2long($ip);

        if ($ipLong === false) {
            return false;
        }

        foreach ($cidrs as $cidr) {
            if (! str_contains($cidr, '/')) {
                if (ip2long($cidr) === $ipLong) {
                    return true;
                }

                continue;
            }

            [$network, $prefix] = explode('/', $cidr, 2);
            $mask = ~((1 << (32 - (int) $prefix)) - 1);

            if (($ipLong & $mask) === (ip2long($network) & $mask)) {
                return true;
            }
        }

        return false;
    }
}
