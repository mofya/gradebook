<?php

namespace Database\Factories;

use App\Models\Assessment;
use App\Models\Course;
use App\Models\Grade;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Grade>
 */
class GradeFactory extends Factory
{
    protected $model = Grade::class;

    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'course_id' => Course::factory(),
            'assessment_id' => Assessment::factory(),
            'grade' => fake()->randomFloat(2, 0, 100),
            'grade_letter' => null,
            'is_published' => false,
        ];
    }
}
