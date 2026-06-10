<?php

declare(strict_types=1);

namespace App\Payments\Presentation\Http\Controllers;

use App\Payments\Infrastructure\Persistence\Models\Refund;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

final class RefundHistoryController extends Controller
{
    /**
     * GET /api/v1/payments/{id}/refunds
     * История всех возвратов по платежу.
     */
    public function __invoke(string $id): JsonResponse
    {
        $refunds = Refund::where('payment_id', $id)
            ->latest()
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'external_id' => $r->external_id,
                'amount' => $r->amount,
                'currency' => $r->currency,
                'reason' => $r->reason,
                'status' => $r->status,
                'created_at' => $r->created_at?->toIso8601String(),
            ]);

        return response()->json(['data' => $refunds]);
    }
}
