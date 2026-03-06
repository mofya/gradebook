<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Semester;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CourseOffering>
 */
class CourseOfferingFactory extends Factory
{
    protected $model = CourseOffering::class;

    public function definition(): array
    {
        return [
            'course_id' => Course::factory(),
            'semester_id' => Semester::factory(),
            'section' => null,
            'grading_scheme_id' => null,
            'lecturer_id' => null,
            'ca_weight' => 50.00,
            'exam_weight' => 50.00,
            'status' => 'draft',
            'is_published' => false,
        ];
    }
}
