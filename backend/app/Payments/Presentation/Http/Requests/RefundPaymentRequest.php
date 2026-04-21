<?php

declare(strict_types=1);

namespace App\Payments\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class RefundPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'amount' => ['sometimes', 'integer', 'min:100'],
            'reason' => ['sometimes', 'string', 'max:255'],
        ];
    }
}
