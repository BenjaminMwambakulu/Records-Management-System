<?php

namespace Database\Factories;

use App\Models\Attendance;
use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Attendance>
 */
class AttendanceFactory extends Factory
{
    protected $model = Attendance::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'user_id' => User::factory(),
            'checked_in_at' => fake()->dateTimeBetween('-1 month', 'now'),
            'checked_in_by' => User::factory(),
        ];
    }

    public function checkedIn(): static
    {
        return $this->state(fn () => [
            'checked_in_at' => fake()->dateTimeBetween('-1 month', 'now'),
            'checked_in_by' => User::factory(),
        ]);
    }
}
