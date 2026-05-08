<?php

declare(strict_types=1);

namespace App\PaymentLinks\Http\Controllers;

use App\PaymentLinks\Models\PaymentLink;
use App\Payments\Application\Bus\CommandBus;
use App\Payments\Application\Commands\CreatePayment\CreatePaymentCommand;
use App\Payments\Application\DTOs\CreatePaymentOptionsDTO;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final class PaymentLinkController extends Controller
{
    public function __construct(private readonly CommandBus $bus) {}

    /**
     * POST /api/v1/payment-links
     * Создать новую ссылку на оплату.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'amount'      => ['required', 'integer', 'min:100'],
            'description' => ['required', 'string', 'max:255'],
            'provider'    => ['sometimes', 'string', 'in:yookassa,robokassa,cloudpayments,sbp,alfabank'],
            'return_url'  => ['sometimes', 'nullable', 'url'],
            'metadata'    => ['sometimes', 'nullable', 'array'],
            'max_uses'    => ['sometimes', 'integer', 'min:1', 'max:1000'],
            'expires_at'  => ['sometimes', 'nullable', 'date', 'after:now'],
        ]);

        $link = PaymentLink::create([
            'token'       => Str::random(32),
            'amount'      => $data['amount'],
            'currency'    => 'RUB',
            'description' => $data['description'],
            'provider'    => $data['provider'] ?? 'yookassa',
            'return_url'  => $data['return_url'] ?? null,
            'metadata'    => $data['metadata'] ?? null,
            'max_uses'    => $data['max_uses'] ?? 1,
            'expires_at'  => isset($data['expires_at']) ? $data['expires_at'] : null,
        ]);

        return response()->json([
            'data' => $this->formatLink($link),
        ], Response::HTTP_CREATED);
    }

    /**
     * GET /api/v1/payment-links
     * Список всех ссылок.
     */
    public function index(): JsonResponse
    {
        $links = PaymentLink::latest()->paginate(20);

        return response()->json([
            'data'  => $links->map(fn ($l) => $this->formatLink($l))->values(),
            'total' => $links->total(),
            'page'  => $links->currentPage(),
        ]);
    }

    /**
     * DELETE /api/v1/payment-links/{id}
     * Деактивировать ссылку (исчерпать лимит).
     */
    public function destroy(string $id): JsonResponse
    {
        $link = PaymentLink::findOrFail($id);
        $link->update(['max_uses' => 0]); // делаем isExhausted() = true

        return response()->json(['message' => 'Payment link deactivated']);
    }

    /**
     * GET /pay/{token}
     * Публичная страница оплаты — показывает Blade-шаблон.
     */
    public function show(string $token)
    {
        $link = PaymentLink::where('token', $token)->firstOrFail();

        if (! $link->isActive()) {
            return view('payment-link-expired', ['link' => $link]);
        }

        return view('payment-link', ['link' => $link]);
    }

    /**
     * POST /pay/{token}
     * Клиент подтверждает оплату — создаём платёж и редиректим.
     */
    public function pay(string $token, Request $request)
    {
        $link = PaymentLink::where('token', $token)->firstOrFail();

        if (! $link->isActive()) {
            return view('payment-link-expired', ['link' => $link]);
        }

        $returnUrl = $link->return_url ?? url('/pay/' . $token . '/success');

        $result = DB::transaction(function () use ($link, $returnUrl) {
            $payment = $this->bus->dispatch(new CreatePaymentCommand(
                amountKopecks:  $link->amount,
                description:    $link->description,
                returnUrl:      $returnUrl,
                idempotencyKey: (string) Str::uuid(),
                userId:         null,
                metadata:       array_merge($link->metadata ?? [], ['payment_link_id' => $link->id]),
                options:        new CreatePaymentOptionsDTO(
                    confirmationType: 'redirect',
                ),
                provider: $link->provider,
            ));

            $link->increment('uses');
            $link->update(['last_payment_id' => $payment->id]);

            return $payment;
        });

        // Редирект на страницу оплаты провайдера
        if ($result->confirmationUrl) {
            return redirect()->away($result->confirmationUrl);
        }

        return redirect('/pay/' . $token . '/success');
    }

    /**
     * GET /pay/{token}/success
     * Страница после успешной оплаты.
     */
    public function success(string $token)
    {
        $link = PaymentLink::where('token', $token)->firstOrFail();

        return view('payment-link-success', ['link' => $link]);
    }

    /** @return array<string, mixed> */
    private function formatLink(PaymentLink $link): array
    {
        return [
            'id'              => $link->id,
            'url'             => url('/pay/' . $link->token),
            'token'           => $link->token,
            'amount'          => $link->amount,
            'currency'        => $link->currency,
            'description'     => $link->description,
            'provider'        => $link->provider,
            'max_uses'        => $link->max_uses,
            'uses'            => $link->uses,
            'is_active'       => $link->isActive(),
            'expires_at'      => $link->expires_at?->toIso8601String(),
            'last_payment_id' => $link->last_payment_id,
            'created_at'      => $link->created_at?->toIso8601String(),
        ];
    }
}
