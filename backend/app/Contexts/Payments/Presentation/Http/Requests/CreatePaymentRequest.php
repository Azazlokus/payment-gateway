<?php

declare(strict_types=1);

namespace App\Contexts\Payments\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CreatePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // Основные поля
            'amount' => ['required', 'integer', 'min:100'],
            'description' => ['required', 'string', 'max:255'],
            'return_url' => ['required', 'url:https'],
            'metadata' => ['sometimes', 'array'],
            'notification_url' => ['sometimes', 'url:https'],

            // Выбор провайдера (опционально, иначе — дефолтный из конфига)
            'provider' => ['sometimes', 'string', Rule::in([
                'yookassa', 'robokassa', 'sbp', 'alfabank', 'cloudpayments',
            ])],

            // Метод оплаты и тип подтверждения
            'payment_method_type' => ['sometimes', 'string', Rule::in([
                'bank_card', 'yoo_money', 'sbp', 'sberbank', 'tinkoff_bank', 'cash',
            ])],
            'confirmation_type' => ['sometimes', 'string', Rule::in(['redirect', 'embedded', 'qr', 'mobile_application'])],
            'save_payment_method' => ['sometimes', 'boolean'],
            'manual_capture' => ['sometimes', 'boolean'],

            // Recurring: списание по сохранённому методу (без редиректа)
            'payment_method_id' => ['sometimes', 'string'],

            // Чек (54-ФЗ) — обязателен если указан хотя бы один item
            'receipt' => ['sometimes', 'array'],
            'receipt.customer.email' => [
                'nullable', 'email',
                Rule::requiredIf(fn (): bool => $this->has('receipt') && ! $this->filled('receipt.customer.phone')),
            ],
            'receipt.customer.phone' => [
                'nullable', 'string',
                Rule::requiredIf(fn (): bool => $this->has('receipt') && ! $this->filled('receipt.customer.email')),
            ],
            'receipt.items' => ['required_with:receipt', 'array', 'min:1'],
            'receipt.items.*.description' => ['required', 'string', 'max:128'],
            'receipt.items.*.quantity' => ['required', 'numeric', 'min:0.001'],
            'receipt.items.*.amount' => ['required', 'integer', 'min:1'],
            'receipt.items.*.vat_code' => ['required', 'integer', Rule::in([1, 2, 3, 4, 5, 6])],
            'receipt.items.*.payment_subject' => ['sometimes', 'string'],
            'receipt.items.*.payment_mode' => ['sometimes', 'string'],

            // Split-платежи (маркетплейс)
            'splits' => ['sometimes', 'array', 'min:1'],
            'splits.*.account_id' => ['required_with:splits', 'string', 'max:64'],
            'splits.*.amount' => ['required_with:splits', 'integer', 'min:100'],
            'splits.*.description' => ['sometimes', 'string', 'max:255'],
        ];
    }
}
