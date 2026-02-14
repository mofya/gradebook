<?php

namespace Database\Factories;

use App\Models\Semester;
use App\Models\Year;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Semester>
 */
class SemesterFactory extends Factory
{
    protected $model = Semester::class;

    public function definition(): array
    {
        return [
            'year_id' => Year::factory(),
            'name' => fake()->randomElement(['Semester 1', 'Semester 2', 'Summer']),
            'start_date' => fake()->date(),
            'end_date' => fake()->date(),
            'is_active' => false,
        ];
    }
}
