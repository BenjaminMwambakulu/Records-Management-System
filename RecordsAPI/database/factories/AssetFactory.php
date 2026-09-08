<?php

namespace Database\Factories;

use App\Enums\AssetStatus;
use App\Models\Asset;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Asset>
 */
class AssetFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->word().' '.fake()->word(),
            'serial_number' => strtoupper(fake()->bothify('??-####')),
            'category' => fake()->randomElement(['Equipment', 'Furniture', 'Electronics', 'Supplies', 'Other']),
            'status' => AssetStatus::AVAILABLE,
            'notes' => fake()->optional(0.3)->sentence(),
        ];
    }

    public function available(): static
    {
        return $this->state(fn () => ['status' => AssetStatus::AVAILABLE]);
    }

    public function borrowed(): static
    {
        return $this->state(fn () => ['status' => AssetStatus::BORROWED]);
    }

    public function maintenance(): static
    {
        return $this->state(fn () => ['status' => AssetStatus::MAINTENANCE]);
    }

    public function retired(): static
    {
        return $this->state(fn () => ['status' => AssetStatus::RETIRED]);
    }
}
