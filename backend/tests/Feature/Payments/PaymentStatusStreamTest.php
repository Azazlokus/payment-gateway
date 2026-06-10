<?php

declare(strict_types=1);

namespace Tests\Feature\Payments;

use App\Payments\Domain\ValueObjects\PaymentId;
use App\Payments\Infrastructure\Persistence\Models\PaymentModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PaymentStatusStreamTest extends TestCase
{
    use RefreshDatabase;

    private function createPayment(string $status = 'Pending'): PaymentModel
    {
        return PaymentModel::create([
            'id' => PaymentId::generate()->toString(),
            'external_id' => (string) Str::uuid(),
            'provider' => 'yookassa',
            'amount' => 10000,
            'refunded_amount' => 0,
            'currency' => 'RUB',
            'status' => $status,
            'description' => 'Test payment',
            'idempotency_key' => (string) Str::uuid(),
            'metadata' => [],
        ]);
    }

    public function test_stream_returns_streamed_response(): void
    {
        $payment = $this->createPayment('Pending');

        $response = $this->get("/api/v1/payments/{$payment->id}/stream");

        $response->assertStatus(200);
    }

    public function test_stream_sets_text_event_stream_content_type(): void
    {
        $payment = $this->createPayment('Pending');

        $response = $this->get("/api/v1/payments/{$payment->id}/stream");

        $this->assertStringContainsString(
            'text/event-stream',
            $response->headers->get('Content-Type') ?? ''
        );
    }

    public function test_stream_sets_cache_control_no_cache(): void
    {
        $payment = $this->createPayment('Pending');

        $response = $this->get("/api/v1/payments/{$payment->id}/stream");

        $this->assertStringContainsString(
            'no-cache',
            $response->headers->get('Cache-Control') ?? ''
        );
    }

    public function test_stream_sets_x_accel_buffering_off(): void
    {
        $payment = $this->createPayment('Pending');

        $response = $this->get("/api/v1/payments/{$payment->id}/stream");

        $this->assertSame('no', $response->headers->get('X-Accel-Buffering'));
    }

    public function test_stream_for_succeeded_payment_sends_close_event(): void
    {
        $payment = $this->createPayment('Succeeded');

        $content = $this->get("/api/v1/payments/{$payment->id}/stream")->streamedContent();

        $this->assertStringContainsString('event: close', $content);
        $this->assertStringContainsString('"status":"Succeeded"', $content);
    }

    public function test_stream_for_cancelled_payment_sends_close_event(): void
    {
        $payment = $this->createPayment('Cancelled');

        $content = $this->get("/api/v1/payments/{$payment->id}/stream")->streamedContent();

        $this->assertStringContainsString('event: close', $content);
        $this->assertStringContainsString('"status":"Cancelled"', $content);
    }

    public function test_stream_for_refunded_payment_sends_close_event(): void
    {
        $payment = $this->createPayment('Refunded');

        $content = $this->get("/api/v1/payments/{$payment->id}/stream")->streamedContent();

        $this->assertStringContainsString('event: close', $content);
        $this->assertStringContainsString('"status":"Refunded"', $content);
    }

    public function test_stream_sends_status_event_with_payment_data(): void
    {
        $payment = $this->createPayment('Succeeded');

        $content = $this->get("/api/v1/payments/{$payment->id}/stream")->streamedContent();

        $this->assertStringContainsString('event: status', $content);
        $this->assertStringContainsString($payment->id, $content);
    }

    public function test_stream_sends_keepalive_comment(): void
    {
        $payment = $this->createPayment('Succeeded');

        $content = $this->get("/api/v1/payments/{$payment->id}/stream")->streamedContent();

        $this->assertStringContainsString(': keepalive', $content);
    }

    public function test_stream_for_nonexistent_payment_sends_error_event(): void
    {
        $fakeId = PaymentId::generate()->toString();

        $content = $this->get("/api/v1/payments/{$fakeId}/stream")->streamedContent();

        $this->assertStringContainsString('event: error', $content);
    }

    public function test_stream_data_is_valid_json(): void
    {
        $payment = $this->createPayment('Succeeded');

        $content = $this->get("/api/v1/payments/{$payment->id}/stream")->streamedContent();

        // Извлекаем data: строки и проверяем что они JSON
        preg_match_all('/^data: (.+)$/m', $content, $matches);

        foreach ($matches[1] as $jsonLine) {
            $decoded = json_decode($jsonLine, true);
            $this->assertNotNull($decoded, "Invalid JSON in SSE data: {$jsonLine}");
        }
    }
}
