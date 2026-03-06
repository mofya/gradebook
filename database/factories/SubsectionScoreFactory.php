<?php

namespace Database\Factories;

use App\Models\AssessmentSubsection;
use App\Models\GradeResult;
use App\Models\SubsectionScore;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SubsectionScore>
 */
class SubsectionScoreFactory extends Factory
{
    protected $model = SubsectionScore::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'grade_result_id' => GradeResult::factory(),
            'assessment_subsection_id' => AssessmentSubsection::factory(),
            'score' => fake()->randomFloat(2, 0, 25),
        ];
    }
}
