<?php

namespace Database\Factories;

use App\Models\Assessment;
use App\Models\Enrollment;
use App\Models\GradeResult;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\GradeResult>
 */
class GradeResultFactory extends Factory
{
    protected $model = GradeResult::class;

    public function definition(): array
    {
        return [
            'enrollment_id' => Enrollment::factory(),
            'assessment_id' => Assessment::factory(),
            'graded_by' => null,
            'raw_score' => fake()->randomFloat(2, 0, 100),
            'normalized_score' => null,
            'is_excused' => false,
            'source' => 'manual',
        ];
    }
}
