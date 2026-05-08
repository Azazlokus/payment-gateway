<?php

declare(strict_types=1);

namespace App\Payments\Presentation\Http\Controllers;

use App\Payments\Domain\Contracts\PaymentRepositoryInterface;
use App\Payments\Domain\ValueObjects\PaymentId;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

final class InvoiceController extends Controller
{
    public function __construct(
        private readonly PaymentRepositoryInterface $repository,
    ) {}

    /**
     * GET /api/v1/payments/{id}/invoice
     * Скачать PDF-квитанцию по платежу.
     */
    public function __invoke(string $id): Response
    {
        try {
            $paymentId = PaymentId::fromString($id);
        } catch (\InvalidArgumentException) {
            abort(404, 'Payment not found');
        }

        $payment = $this->repository->findById($paymentId);

        if ($payment === null) {
            abort(404, 'Payment not found');
        }

        $pdf = Pdf::loadView('invoice', ['payment' => $payment])
            ->setPaper('a4', 'portrait');

        $filename = 'invoice-' . strtoupper(substr($id, -8)) . '.pdf';

        return $pdf->download($filename);
    }
}
