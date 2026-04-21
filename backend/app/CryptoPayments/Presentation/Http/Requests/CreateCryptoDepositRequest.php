<?php

declare(strict_types=1);

namespace App\CryptoPayments\Presentation\Http\Requests;

use App\CryptoPayments\Domain\Enums\CryptoAsset;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CreateCryptoDepositRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $assetValues = array_map(fn (CryptoAsset $a) => $a->value, CryptoAsset::cases());

        return [
            'payment_id'          => ['required', 'string', 'max:255'],
            'fiat_amount_kopecks' => ['required', 'integer', 'min:100'],
            'asset'               => ['required', 'string', Rule::in($assetValues)],
        ];
    }
}
