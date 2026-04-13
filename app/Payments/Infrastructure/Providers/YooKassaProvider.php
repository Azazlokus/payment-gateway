<?php

declare(strict_types=1);

namespace App\Payments\Infrastructure\Providers;

use App\Payments\Application\DTOs\CreatePaymentOptionsDTO;
use App\Payments\Application\DTOs\ReceiptItemDTO;
use App\Payments\Domain\Contracts\PaymentProviderInterface;
use App\Payments\Domain\Contracts\ProviderResponse;
use App\Payments\Domain\Exceptions\PaymentException;
use App\Payments\Domain\ValueObjects\ExternalId;
use App\Payments\Domain\ValueObjects\Money;
use App\Payments\Infrastructure\Observability\PaymentLogger;
use YooKassa\Client;

final class YooKassaProvider implements PaymentProviderInterface
{
    private readonly Client $client;

    public function __construct(
        private readonly string        $shopId,
        private readonly string        $secretKey,
        private readonly PaymentLogger $logger,
        ?Client                        $client = null,
    ) {
        $this->client = $client ?? new Client();
        $this->client->setAuth((int) $this->shopId, $this->secretKey);
    }

    public function name(): string
    {
        return 'yookassa';
    }

    public function createPayment(
        string                  $paymentId,
        Money                   $amount,
        string                  $description,
        string                  $returnUrl,
        string                  $idempotencyKey,
        CreatePaymentOptionsDTO $options = new CreatePaymentOptionsDTO(),
    ): ProviderResponse {
        try {
            $this->logger->info('YooKassa: создание платежа', [
                'payment_id'      => $paymentId,
                'amount'          => $amount->formatted(),
                'idempotency_key' => $idempotencyKey,
                'save_method'     => $options->savePaymentMethod,
            ]);

            $payload = [
                'amount' => [
                    'value'    => number_format($amount->amount() / 100, 2, '.', ''),
                    'currency' => $amount->currency()->value,
                ],
                'description'          => $description,
                'metadata'             => ['internal_payment_id' => $paymentId],
                'capture'              => true,
                'save_payment_method'  => $options->savePaymentMethod,
            ];

            // Recurring: используем сохранённый метод — без подтверждения
            if ($options->paymentMethodId !== null) {
                $payload['payment_method_id'] = $options->paymentMethodId;
            } else {
                $payload['confirmation'] = $this->buildConfirmation($options, $returnUrl);

                if ($options->paymentMethodType !== null) {
                    $payload['payment_method_data'] = ['type' => $options->paymentMethodType];
                }
            }

            if ($options->receipt !== null) {
                $payload['receipt'] = $this->buildReceipt($options, $amount);
            }

            $response = retry(
                times: 3,
                callback: fn() => $this->client->createPayment($payload, $idempotencyKey),
                sleepMilliseconds: 500,
                when: fn(\Throwable $e) => $this->isRetryable($e),
            );

            $this->logger->info('YooKassa: платёж создан', [
                'payment_id'  => $paymentId,
                'external_id' => $response->getId(),
                'status'      => $response->getStatus(),
            ]);

            $paymentMethodId = null;
            if ($options->savePaymentMethod && $response->getPaymentMethod()?->getSaved()) {
                $paymentMethodId = $response->getPaymentMethod()->getId();
            }

            $confirmationUrl = $options->paymentMethodId !== null
                ? ''
                : ($response->getConfirmation()?->getConfirmationUrl() ?? '');

            return new ProviderResponse(
                externalId:      ExternalId::fromString($response->getId()),
                confirmationUrl: $confirmationUrl,
                status:          $response->getStatus(),
                paymentMethodId: $paymentMethodId,
                rawData:         $response->jsonSerialize(),
            );
        } catch (PaymentException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $this->logger->error('YooKassa: ошибка создания платежа', [
                'payment_id' => $paymentId,
                'error'      => $e->getMessage(),
            ]);

            throw new PaymentException("YooKassa createPayment failed: {$e->getMessage()}", previous: $e);
        }
    }

    public function getPayment(ExternalId $externalId): ProviderResponse
    {
        try {
            $response = $this->client->getPaymentInfo($externalId->toString());

            return new ProviderResponse(
                externalId:      ExternalId::fromString($response->getId()),
                confirmationUrl: '',
                status:          $response->getStatus(),
                paymentMethodId: $response->getPaymentMethod()?->getId(),
                rawData:         $response->jsonSerialize(),
            );
        } catch (\Throwable $e) {
            throw new PaymentException("YooKassa getPayment failed: {$e->getMessage()}", previous: $e);
        }
    }

    public function refundPayment(ExternalId $externalId, Money $amount): ProviderResponse
    {
        try {
            $response = retry(
                times: 3,
                callback: fn() => $this->client->createRefund(
                    [
                        'payment_id' => $externalId->toString(),
                        'amount'     => [
                            'value'    => number_format($amount->amount() / 100, 2, '.', ''),
                            'currency' => $amount->currency()->value,
                        ],
                    ],
                    (string) \Illuminate\Support\Str::uuid(),
                ),
                sleepMilliseconds: 500,
                when: fn(\Throwable $e) => $this->isRetryable($e),
            );

            return new ProviderResponse(
                externalId:      ExternalId::fromString($response->getId()),
                confirmationUrl: '',
                status:          $response->getStatus(),
                rawData:         $response->jsonSerialize(),
            );
        } catch (\Throwable $e) {
            throw new PaymentException("YooKassa refund failed: {$e->getMessage()}", previous: $e);
        }
    }

    public function verifyWebhook(array $payload, array $headers): bool
    {
        $allowedCidrs = config('payments.yookassa.webhook_ips', []);
        $requestIp    = request()->ip();

        if (!empty($allowedCidrs) && !$this->ipInAllowedRanges($requestIp, $allowedCidrs)) {
            $this->logger->warning('YooKassa: webhook с неизвестного IP', ['ip' => $requestIp]);
            return false;
        }

        return isset($payload['event'], $payload['object']['id']);
    }

    public function parseWebhook(array $payload): ProviderResponse
    {
        $object = $payload['object'];
        $event  = $payload['event'] ?? '';

        // Для refund.* событий object['id'] — это ID рефанда, а не платежа.
        // Платёж нужно искать по object['payment_id'].
        if (str_starts_with($event, 'refund.')) {
            $kopecks = isset($object['amount']['value'])
                ? (int) round((float) $object['amount']['value'] * 100)
                : null;

            return new ProviderResponse(
                externalId:          ExternalId::fromString($object['payment_id']),
                confirmationUrl:     '',
                status:              $object['status'],
                refundAmountKopecks: $kopecks,
                rawData:             $payload,
            );
        }

        return new ProviderResponse(
            externalId:      ExternalId::fromString($object['id']),
            confirmationUrl: '',
            status:          $object['status'],
            paymentMethodId: $object['payment_method']['id'] ?? null,
            rawData:         $payload,
        );
    }

    // ─── Private helpers ────────────────────────────────────────────────────

    private function buildConfirmation(CreatePaymentOptionsDTO $options, string $returnUrl): array
    {
        return match ($options->confirmationType) {
            'qr' => ['type' => 'qr'],
            'embedded' => ['type' => 'embedded'],
            'mobile_application' => ['type' => 'mobile_application', 'return_url' => $returnUrl],
            default => ['type' => 'redirect', 'return_url' => $returnUrl],
        };
    }

    private function buildReceipt(CreatePaymentOptionsDTO $options, Money $amount): array
    {
        $receipt = $options->receipt;

        $customer = array_filter([
            'email' => $receipt->email,
            'phone' => $receipt->phone,
        ]);

        $items = array_map(fn(ReceiptItemDTO $item) => [
            'description'     => $item->description,
            'quantity'        => $item->quantity,
            'amount'          => [
                'value'    => number_format($item->amountKopecks / 100, 2, '.', ''),
                'currency' => $amount->currency()->value,
            ],
            'vat_code'        => $item->vatCode,
            'payment_subject' => $item->paymentSubject,
            'payment_mode'    => $item->paymentMode,
        ], $receipt->items);

        return ['customer' => $customer, 'items' => $items];
    }

    private function isRetryable(\Throwable $e): bool
    {
        // Ретраим только на 5xx ошибки сервера YooKassa
        if (method_exists($e, 'getCode') && $e->getCode() >= 500) {
            return true;
        }

        return str_contains($e->getMessage(), 'cURL error') ||
               str_contains($e->getMessage(), 'timed out');
    }

    private function ipInAllowedRanges(string $ip, array $cidrs): bool
    {
        $ipLong = ip2long($ip);

        if ($ipLong === false) {
            return false;
        }

        foreach ($cidrs as $cidr) {
            if (!str_contains($cidr, '/')) {
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
