<?php

namespace Database\Factories;

use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Enrollment>
 */
class EnrollmentFactory extends Factory
{
    protected $model = Enrollment::class;

    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'course_offering_id' => CourseOffering::factory(),
            'source' => 'manual',
            'status' => 'enrolled',
            'exam_status' => null,
            'ca_override_reason' => null,
            'final_override' => null,
            'final_override_reason' => null,
        ];
    }
}
