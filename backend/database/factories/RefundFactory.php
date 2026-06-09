<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Payments\Domain\Enums\RefundStatus;
use App\Payments\Infrastructure\Persistence\Models\Refund;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Refund> */
final class RefundFactory extends Factory
{
    protected $model = Refund::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'payment_id' => (string) Str::ulid(),
            'amount' => $this->faker->numberBetween(100, 100_000),
            'currency' => 'RUB',
            'reason' => $this->faker->sentence(),
            'status' => RefundStatus::Pending,
            'idempotency_key' => (string) Str::uuid(),
            'attempts' => 0,
        ];
    }
}
