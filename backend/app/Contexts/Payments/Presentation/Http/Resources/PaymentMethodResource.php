<?php

declare(strict_types=1);

namespace App\Contexts\Payments\Presentation\Http\Resources;

use App\Contexts\Payments\Domain\Aggregates\PaymentMethod;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PaymentMethod */
final class PaymentMethodResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var PaymentMethod $method */
        $method = $this->resource;

        return [
            'id' => $method->id()->toString(),
            'customer_id' => $method->customerId(),
            'provider' => $method->provider(),
            'type' => $method->type()->value,
            'last4' => $method->last4(),
            'brand' => $method->brand(),
            'expires_at' => $method->expiresAt(),
            'is_active' => $method->isActive(),
        ];
    }
}
