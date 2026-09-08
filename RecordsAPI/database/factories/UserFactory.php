<?php

namespace Database\Factories;

use App\Enums\AcademicTrack;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'logto_id' => fake()->unique()->uuid(),
            'student_id' => 'STU-'.fake()->unique()->numberBetween(1000, 9999),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'academic_track' => fake()->randomElement([AcademicTrack::Bit, AcademicTrack::Css]),
            'enrolled_year' => fake()->numberBetween(2022, 2026),
            'study_year' => fake()->numberBetween(1, 4),
            'skills' => [],
        ];
    }
}
