<?php

declare(strict_types=1);

namespace App\Contexts\Payments\Presentation\Http\Resources;

use App\Contexts\Payments\Application\DTOs\PaymentResultDTO;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class PaymentResource extends JsonResource
{
    public function __construct(private readonly PaymentResultDTO $dto)
    {
        parent::__construct($dto);
    }

    /** @return array<string, mixed> */
    #[\Override]
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->dto->paymentId,
            'status' => $this->dto->status,
            'amount' => $this->dto->amount,
            'currency' => $this->dto->currency,
            'confirmation_url' => $this->dto->confirmationUrl,
            'external_id' => $this->dto->externalId,
            'payment_method_id' => $this->dto->paymentMethodId,
            'refunded_amount' => $this->dto->refundedAmount,
            'captured_amount' => $this->dto->capturedAmount,
            'three_ds_required' => $this->dto->threeDsRequired,
            'three_ds_challenge_url' => $this->dto->threeDsChallengeUrl,
            'splits' => $this->dto->splits,
        ];
    }
}
