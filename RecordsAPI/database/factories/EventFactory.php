<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Event>
 */
class EventFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(3),
            'slug' => fake()->unique()->slug(2),
            'description' => fake()->paragraph(),
            'location' => fake()->city(),
            'event_date' => fake()->dateTimeBetween('+1 week', '+1 year'),
            'start_time' => fake()->time('H:i'),
            'duration' => fake()->randomElement([60, 90, 120, 180, 240]),
            'entry_fee' => fake()->randomFloat(2, 0, 50),
            'budget' => fake()->randomFloat(2, 100, 5000),
            'qr_code_hash' => Str::random(32),
            'is_published' => fake()->boolean(),
            'created_by' => User::factory(),
        ];
    }
}
