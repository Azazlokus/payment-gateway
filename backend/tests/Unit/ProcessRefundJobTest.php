<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Payments\Domain\Enums\RefundStatus;
use App\Payments\Infrastructure\Persistence\Models\Refund;
use PHPUnit\Framework\TestCase;

/**
 * Pure unit tests for RefundStatus enum and Refund model methods.
 * ProcessRefundJob integration tests require Laravel bootstrap (Feature suite).
 */
class ProcessRefundJobTest extends TestCase
{
    public function test_refund_status_pending_is_not_terminal(): void
    {
        $this->assertFalse(RefundStatus::Pending->isTerminal());
    }

    public function test_refund_status_processing_is_not_terminal(): void
    {
        $this->assertFalse(RefundStatus::Processing->isTerminal());
    }

    public function test_refund_status_succeeded_is_terminal(): void
    {
        $this->assertTrue(RefundStatus::Succeeded->isTerminal());
    }

    public function test_refund_status_failed_is_terminal(): void
    {
        $this->assertTrue(RefundStatus::Failed->isTerminal());
    }

    public function test_refund_status_requires_review_is_not_terminal(): void
    {
        $this->assertFalse(RefundStatus::RequiresReview->isTerminal());
    }

    public function test_all_status_values(): void
    {
        $values = array_map(fn (RefundStatus $s) => $s->value, RefundStatus::cases());

        $this->assertSame(['pending', 'processing', 'succeeded', 'failed', 'requires_review'], $values);
    }
}
