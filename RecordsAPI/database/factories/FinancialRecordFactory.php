<?php

namespace Database\Factories;

use App\Enums\TransactionType;
use App\Models\FinancialCategory;
use App\Models\FinancialRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FinancialRecord>
 */
class FinancialRecordFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(3),
            'type' => fake()->randomElement(TransactionType::cases()),
            'amount' => fake()->randomFloat(2, 1, 10000),
            'transaction_date' => fake()->date(),
            'category_id' => FinancialCategory::factory(),
            'recorded_by' => User::factory(),
        ];
    }
}
