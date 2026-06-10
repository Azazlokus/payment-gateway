<?php

declare(strict_types=1);

namespace App\Payments\Presentation\Http\Controllers;

use App\Payments\Application\DTOs\PaymentResultDTO;
use App\Payments\Domain\Contracts\PaymentRepositoryInterface;
use App\Payments\Domain\Enums\PaymentStatus;
use App\Payments\Domain\ValueObjects\PaymentId;
use App\Payments\Presentation\Http\Resources\PaymentResource;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * SSE-стрим статуса платежа.
 *
 * Клиент открывает EventSource на этот URL и получает обновления в реальном
 * времени без поллинга со своей стороны. Соединение закрывается автоматически,
 * когда платёж переходит в терминальный статус.
 *
 * Пример (JS):
 *   const es = new EventSource('/api/v1/payments/01HX.../stream');
 *   es.onmessage = e => console.log(JSON.parse(e.data));
 *   es.addEventListener('close', () => es.close());
 */
final class PaymentStatusStreamController extends Controller
{
    // Статусы, после которых стрим закрывается
    private const TERMINAL_STATUSES = [
        PaymentStatus::Succeeded,
        PaymentStatus::Cancelled,
        PaymentStatus::Refunded,
    ];

    // Интервал между проверками в секундах
    private const POLL_INTERVAL = 2;

    // Максимальное время жизни стрима (5 минут)
    private const MAX_SECONDS = 300;

    public function __construct(
        private readonly PaymentRepositoryInterface $repository,
    ) {}

    public function __invoke(Request $request, string $id): StreamedResponse
    {
        $paymentId = PaymentId::fromString($id);

        $response = new StreamedResponse(function () use ($paymentId) {
            $started = time();

            // Отправляем initial keepalive, чтобы клиент сразу получил соединение
            $this->sendKeepAlive();

            while (true) {
                // Таймаут — закрываем соединение, клиент переоткроет
                if (time() - $started >= self::MAX_SECONDS) {
                    $this->sendEvent('timeout', ['message' => 'Stream timeout. Reconnect to continue.']);
                    break;
                }

                try {
                    $payment = $this->repository->findById($paymentId);
                } catch (\Throwable) {
                    $this->sendEvent('error', ['message' => 'Payment not found']);
                    break;
                }

                $resource = new PaymentResource(PaymentResultDTO::fromAggregate($payment));
                $this->sendEvent('status', $resource->resolve());

                if (in_array($payment->status(), self::TERMINAL_STATUSES, strict: true)) {
                    // Терминальный статус — сигнализируем клиенту закрыть EventSource
                    $this->sendEvent('close', ['status' => $payment->status()->value]);
                    break;
                }

                // Flush и ждём следующую итерацию
                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();

                sleep(self::POLL_INTERVAL);
            }
        });

        $response->headers->set('Content-Type', 'text/event-stream');
        $response->headers->set('Cache-Control', 'no-cache');
        $response->headers->set('X-Accel-Buffering', 'no'); // отключает буферизацию Nginx
        $response->headers->set('Connection', 'keep-alive');

        return $response;
    }

    /** @param array<string, mixed> $data */
    private function sendEvent(string $event, array $data): void
    {
        echo "event: {$event}\n";
        echo 'data: '.json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n\n";
    }

    private function sendKeepAlive(): void
    {
        echo ": keepalive\n\n";

        if (ob_get_level() > 0) {
            ob_flush();
        }
        flush();
    }
}
