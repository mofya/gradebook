<?php

namespace Database\Factories;

use App\Models\Assessment;
use App\Models\Course;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Assessment>
 */
class AssessmentFactory extends Factory
{
    protected $model = Assessment::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'weight' => fake()->randomFloat(2, 5, 50),
            'course_id' => Course::factory(),
            'max_raw_score' => 100,
            'normalized_to' => null,
            'due_date' => null,
            'has_subsections' => false,
            'is_published' => false,
            'sort_order' => 0,
        ];
    }
}
