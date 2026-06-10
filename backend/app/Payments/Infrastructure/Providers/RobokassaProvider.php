<?php

declare(strict_types=1);

namespace App\Payments\Infrastructure\Providers;

use App\Payments\Application\DTOs\CreatePaymentOptionsDTO;
use App\Payments\Domain\Contracts\PaymentProviderInterface;
use App\Payments\Domain\Contracts\ProviderResponse;
use App\Payments\Domain\Exceptions\PaymentException;
use App\Payments\Domain\ValueObjects\ExternalId;
use App\Payments\Domain\ValueObjects\Money;
use App\Payments\Infrastructure\Observability\PaymentLogger;
use Illuminate\Support\Facades\Http;

final class RobokassaProvider implements PaymentProviderInterface
{
    private const BASE_URL = 'https://auth.robokassa.ru/Merchant';

    public function __construct(
        private readonly string $login,
        private readonly string $password1,
        private readonly string $password2,
        private readonly bool $isTest,
        private readonly PaymentLogger $logger,
    ) {}

    public function name(): string
    {
        return 'robokassa';
    }

    public function createPayment(
        string $paymentId,
        Money $amount,
        string $description,
        string $returnUrl,
        string $idempotencyKey,
        CreatePaymentOptionsDTO $options = new CreatePaymentOptionsDTO,
    ): ProviderResponse {
        $outSum = $this->kopecksToRubles($amount->amount());
        $invId = 0; // Robokassa auto-assigns; the real InvId arrives in the webhook

        $signature = md5("{$this->login}:{$outSum}:{$invId}:{$this->password1}:Shp_paymentId={$paymentId}");

        $params = http_build_query([
            'MerchantLogin' => $this->login,
            'OutSum' => $outSum,
            'InvId' => $invId,
            'Description' => mb_substr($description, 0, 100),
            'SignatureValue' => $signature,
            'IsTest' => $this->isTest ? 1 : 0,
            'Shp_paymentId' => $paymentId,
        ]);

        $confirmationUrl = self::BASE_URL.'/Index.aspx?'.$params;

        $this->logger->info('Robokassa: создание платежа', [
            'payment_id' => $paymentId,
            'amount' => $outSum,
        ]);

        // Use internal paymentId as a placeholder external_id until the real InvId arrives via webhook
        return new ProviderResponse(
            externalId: ExternalId::fromString($paymentId),
            confirmationUrl: $confirmationUrl,
            status: 'pending',
        );
    }

    public function getPayment(ExternalId $externalId): ProviderResponse
    {
        throw new PaymentException(
            'Robokassa does not support payment status polling. Use webhooks instead.',
            501,
        );
    }

    public function refundPayment(ExternalId $externalId, Money $amount): ProviderResponse
    {
        $invId = $externalId->toString();
        $outSum = $this->kopecksToRubles($amount->amount());
        $signature = md5("{$this->login}:{$outSum}:{$invId}:{$this->password1}");

        $response = Http::asForm()->post(self::BASE_URL.'/Payment/Return', [
            'MerchantLogin' => $this->login,
            'InvoiceID' => $invId,
            'Amount' => $outSum,
            'Signature' => $signature,
        ]);

        if (! $response->successful()) {
            throw new PaymentException(
                "Robokassa refund failed [HTTP {$response->status()}]: {$response->body()}"
            );
        }

        $this->logger->info('Robokassa: возврат создан', [
            'inv_id' => $invId,
            'amount' => $outSum,
        ]);

        return new ProviderResponse(
            externalId: $externalId,
            confirmationUrl: '',
            status: 'succeeded',
            rawData: ['response' => $response->body()],
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, list<string|null>>  $headers
     */
    public function verifyWebhook(array $payload, array $headers): bool
    {
        $allowedCidrs = config('payments.robokassa.webhook_ips', []);
        $requestIp = request()->ip();

        if (! empty($allowedCidrs) && ! $this->ipInAllowedRanges($requestIp, $allowedCidrs)) {
            $this->logger->warning('Robokassa: webhook с неизвестного IP', ['ip' => $requestIp]);

            return false;
        }

        $outSum = (string) ($payload['OutSum'] ?? '');
        $invId = (string) ($payload['InvId'] ?? '');
        $paymentId = (string) ($payload['Shp_paymentId'] ?? '');
        $received = strtoupper((string) ($payload['SignatureValue'] ?? ''));

        $expected = strtoupper(md5("{$outSum}:{$invId}:{$this->password2}:Shp_paymentId={$paymentId}"));

        if ($received !== $expected) {
            $this->logger->warning('Robokassa: невалидная подпись вебхука', ['ip' => $requestIp]);

            return false;
        }

        return $paymentId !== '';
    }

    /** @param array<string, mixed> $payload */
    public function parseWebhook(array $payload): ProviderResponse
    {
        $paymentId = (string) ($payload['Shp_paymentId'] ?? '');
        $invId = (string) ($payload['InvId'] ?? '0');
        $outSum = (string) ($payload['OutSum'] ?? '0');

        return new ProviderResponse(
            externalId: ExternalId::fromString($paymentId),
            confirmationUrl: '',
            status: 'succeeded',
            rawData: array_merge($payload, ['inv_id' => $invId, 'out_sum' => $outSum]),
        );
    }

    // ─── Private helpers ─────────────────────────────────────────────────────

    private function kopecksToRubles(int $kopecks): string
    {
        return number_format($kopecks / 100, 2, '.', '');
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
