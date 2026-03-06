<?php

namespace Database\Factories;

use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Student>
 */
class StudentFactory extends Factory
{
    protected $model = Student::class;

    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'student_id_number' => fake()->unique()->numerify('SN#########'),
            'gender' => fake()->randomElement(['Male', 'Female']),
            'program' => fake()->randomElement(['Computer Science', 'Engineering', 'Business', 'Medicine', 'Law']),
            'year_of_study' => fake()->numberBetween(1, 5),
            'study_mode' => fake()->randomElement(['Full-time', 'Part-time', 'Distance']),
        ];
    }
}
