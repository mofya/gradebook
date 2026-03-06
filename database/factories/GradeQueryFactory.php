<?php

namespace Database\Factories;

use App\Models\Enrollment;
use App\Models\GradeQuery;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\GradeQuery>
 */
class GradeQueryFactory extends Factory
{
    protected $model = GradeQuery::class;

    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'enrollment_id' => Enrollment::factory(),
            'assessment_id' => null,
            'subject' => fake()->sentence(),
            'status' => 'open',
            'priority' => 'normal',
            'assigned_to' => null,
            'student_message' => fake()->paragraph(),
        ];
    }
}
