<?php

namespace Database\Factories;

use App\Models\AssessmentGroup;
use App\Models\CourseOffering;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AssessmentGroup>
 */
class AssessmentGroupFactory extends Factory
{
    protected $model = AssessmentGroup::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'course_offering_id' => CourseOffering::factory(),
            'name' => fake()->randomElement(['Continuous Assessment', 'Examination']),
            'type' => fake()->randomElement(['ca', 'exam']),
            'weight_percentage' => 50.00,
            'weight_points' => null,
            'weight_mode' => 'percentage',
            'sort_order' => 0,
        ];
    }
}
