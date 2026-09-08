<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

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
            'is_published' => fake()->boolean(),
            'created_by' => User::factory(),
        ];
    }
}
