<?php

namespace Database\Factories;

use App\Models\GradingScheme;
use App\Models\GradingSchemeLevel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\GradingSchemeLevel>
 */
class GradingSchemeLevelFactory extends Factory
{
    protected $model = GradingSchemeLevel::class;

    public function definition(): array
    {
        return [
            'grading_scheme_id' => GradingScheme::factory(),
            'letter' => 'A',
            'min_mark' => 80,
            'max_mark' => 100,
            'grade_points' => 4.0,
            'sort_order' => 0,
        ];
    }
}
