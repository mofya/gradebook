<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\Year;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Course>
 */
class CourseFactory extends Factory
{
    protected $model = Course::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'code' => fake()->unique()->bothify('???-###'),
            'year_id' => Year::factory(),
            'credits' => fake()->numberBetween(1, 6),
        ];
    }
}
