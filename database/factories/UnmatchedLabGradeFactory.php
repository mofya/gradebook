<?php

namespace Database\Factories;

use App\Models\Assessment;
use App\Models\CourseOffering;
use App\Models\UnmatchedLabGrade;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UnmatchedLabGrade>
 */
class UnmatchedLabGradeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'course_offering_id' => CourseOffering::factory(),
            'assessment_id' => Assessment::factory(),
            'github_username' => fake()->userName(),
            'row_data' => [
                'GitHub Username' => fake()->userName(),
                'Final Score (%)' => fake()->randomFloat(2, 0, 100),
                'Letter Grade' => 'A',
            ],
            'status' => 'pending',
        ];
    }

    public function matched(): static
    {
        return $this->state(fn () => [
            'status' => 'matched',
            'matched_at' => now(),
        ]);
    }
}
