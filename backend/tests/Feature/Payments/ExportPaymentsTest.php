<?php

declare(strict_types=1);

namespace Tests\Feature\Payments;

use App\Payments\Domain\ValueObjects\PaymentId;
use App\Payments\Infrastructure\Persistence\Models\PaymentModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ExportPaymentsTest extends TestCase
{
    use RefreshDatabase;

    private function createPayment(string $status = 'Succeeded', string $provider = 'yookassa'): void
    {
        PaymentModel::create([
            'id'              => PaymentId::generate()->toString(),
            'external_id'     => (string) Str::uuid(),
            'provider'        => $provider,
            'amount'          => 50000,
            'refunded_amount' => 0,
            'currency'        => 'RUB',
            'status'          => $status,
            'description'     => 'Test payment',
            'idempotency_key' => (string) Str::uuid(),
            'metadata'        => [],
        ]);
    }

    public function test_export_returns_csv_content_type(): void
    {
        $this->createPayment();

        $response = $this->get('/api/payments/export');

        $response->assertStatus(200);
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type') ?? '');
    }

    public function test_export_contains_csv_headers(): void
    {
        $response = $this->get('/api/payments/export');

        $content = $response->streamedContent();
        $this->assertStringContainsString('id,status,provider,amount,currency,description,external_id', $content);
    }

    public function test_export_contains_payment_rows(): void
    {
        $this->createPayment('Succeeded', 'yookassa');
        $this->createPayment('Pending', 'robokassa');

        $content = $this->get('/api/payments/export')->streamedContent();

        $lines = array_filter(explode("\n", trim($content)));
        // 1 header + 2 payments
        $this->assertCount(3, $lines);
    }

    public function test_export_filters_by_status(): void
    {
        $this->createPayment('Succeeded');
        $this->createPayment('Pending');

        $content = $this->get('/api/payments/export?status=Succeeded')->streamedContent();

        $lines = array_filter(explode("\n", trim($content)));
        $this->assertCount(2, $lines); // header + 1 row
        $this->assertStringContainsString('Succeeded', $content);
    }

    public function test_export_filters_by_provider(): void
    {
        $this->createPayment('Succeeded', 'yookassa');
        $this->createPayment('Succeeded', 'robokassa');

        $content = $this->get('/api/payments/export?provider=robokassa')->streamedContent();

        $lines = array_filter(explode("\n", trim($content)));
        $this->assertCount(2, $lines); // header + 1 row
        $this->assertStringContainsString('robokassa', $content);
    }

    public function test_export_empty_dataset_returns_only_headers(): void
    {
        $content = $this->get('/api/payments/export')->streamedContent();

        $lines = array_filter(explode("\n", trim($content)));
        $this->assertCount(1, $lines);
        $this->assertStringContainsString('id', $lines[array_key_first($lines)]);
    }

    public function test_export_csv_columns_in_correct_order(): void
    {
        $content = $this->get('/api/payments/export')->streamedContent();

        $firstLine = explode("\n", trim($content))[0];
        $columns   = str_getcsv($firstLine);

        $this->assertSame(['id', 'status', 'provider', 'amount', 'currency', 'description', 'external_id', 'created_at'], $columns);
    }

    public function test_export_content_disposition_header_contains_filename(): void
    {
        $response = $this->get('/api/payments/export');

        $contentDisposition = $response->headers->get('Content-Disposition') ?? '';
        $this->assertStringContainsString('payments-', $contentDisposition);
        $this->assertStringContainsString('.csv', $contentDisposition);
    }

    public function test_export_filters_by_date_from(): void
    {
        // Платёж с датой в прошлом
        PaymentModel::create([
            'id'              => PaymentId::generate()->toString(),
            'external_id'     => (string) Str::uuid(),
            'provider'        => 'yookassa',
            'amount'          => 50000,
            'refunded_amount' => 0,
            'currency'        => 'RUB',
            'status'          => 'Succeeded',
            'description'     => 'Old payment',
            'idempotency_key' => (string) Str::uuid(),
            'metadata'        => [],
            'created_at'      => now()->subDays(10),
            'updated_at'      => now()->subDays(10),
        ]);

        // Платёж сегодня
        $this->createPayment();

        $fromDate = now()->subDays(1)->format('Y-m-d');
        $content  = $this->get("/api/payments/export?from_date={$fromDate}")->streamedContent();

        $lines = array_filter(explode("\n", trim($content)));
        $this->assertCount(2, $lines); // header + 1 новый платёж
    }

    public function test_export_filters_by_date_to(): void
    {
        // Платёж с датой в прошлом
        PaymentModel::create([
            'id'              => PaymentId::generate()->toString(),
            'external_id'     => (string) Str::uuid(),
            'provider'        => 'yookassa',
            'amount'          => 50000,
            'refunded_amount' => 0,
            'currency'        => 'RUB',
            'status'          => 'Succeeded',
            'description'     => 'Old payment',
            'idempotency_key' => (string) Str::uuid(),
            'metadata'        => [],
            'created_at'      => now()->subDays(10),
            'updated_at'      => now()->subDays(10),
        ]);

        // Платёж сегодня
        $this->createPayment();

        $toDate  = now()->subDays(5)->format('Y-m-d');
        $content = $this->get("/api/payments/export?to_date={$toDate}")->streamedContent();

        $lines = array_filter(explode("\n", trim($content)));
        $this->assertCount(2, $lines); // header + 1 старый платёж
    }
}
