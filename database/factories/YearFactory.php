<?php

namespace Database\Factories;

use App\Models\Year;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Year>
 */
class YearFactory extends Factory
{
    protected $model = Year::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->numerify('20##-20##'),
            'start_date' => null,
            'end_date' => null,
            'is_current' => false,
        ];
    }
}
