<?php

namespace Database\Factories;

use App\Enums\PaymentStatus;
use App\Models\Event;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'payable_type' => Event::class,
            'payable_id' => Event::factory(),
            'user_id' => User::factory(),
            'amount' => fake()->randomFloat(2, 10, 500),
            'currency' => 'PHP',
            'status' => PaymentStatus::PENDING,
            'payment_method' => fake()->randomElement(['card', 'bank_transfer', 'cash', 'gcash', 'paymaya']),
            'payment_carrier' => fake()->randomElement(['visa', 'mastercard', 'bdo', 'bpi', 'paychangu']),
            'tx_ref' => 'TX-'.Str::random(10),
            'provider_reference' => null,
            'provider_response' => null,
            'metadata' => null,
            'paid_at' => null,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => ['status' => PaymentStatus::PENDING]);
    }

    public function processing(): static
    {
        return $this->state(fn () => ['status' => PaymentStatus::PROCESSING]);
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => PaymentStatus::COMPLETED,
            'paid_at' => fake()->dateTimeBetween('-1 month', 'now'),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn () => ['status' => PaymentStatus::FAILED]);
    }

    public function refunded(): static
    {
        return $this->state(fn () => ['status' => PaymentStatus::REFUNDED]);
    }
}
