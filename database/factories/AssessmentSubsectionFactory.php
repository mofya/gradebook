<?php

namespace Database\Factories;

use App\Models\Assessment;
use App\Models\AssessmentSubsection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AssessmentSubsection>
 */
class AssessmentSubsectionFactory extends Factory
{
    protected $model = AssessmentSubsection::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'assessment_id' => Assessment::factory(),
            'name' => 'Question '.fake()->numberBetween(1, 10),
            'max_score' => fake()->randomFloat(2, 5, 25),
            'sort_order' => 0,
        ];
    }
}
