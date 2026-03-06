<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Department>
 */
class DepartmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'dept_name' => fake()->unique()->words(3, true),
            'dept_code' => fake()->unique()->bothify('??##'),
        ];
    }
}
