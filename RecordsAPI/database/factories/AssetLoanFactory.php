<?php

namespace Database\Factories;

use App\Models\Asset;
use App\Models\AssetLoan;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssetLoan>
 */
class AssetLoanFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'asset_id' => Asset::factory(),
            'borrower_id' => User::factory(),
            'issued_by' => User::factory(),
            'checkout_date' => fake()->dateTimeBetween('-1 month', 'now'),
            'due_date' => fake()->dateTimeBetween('+1 week', '+1 month'),
            'returned_date' => null,
        ];
    }

    public function returned(): static
    {
        return $this->state(fn () => [
            'returned_date' => Carbon::now()->subDays(fake()->numberBetween(1, 7)),
        ]);
    }

    public function overdue(): static
    {
        return $this->state(fn () => [
            'due_date' => Carbon::now()->subDays(fake()->numberBetween(1, 7)),
            'returned_date' => null,
        ]);
    }
}
