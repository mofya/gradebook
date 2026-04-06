<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Semester;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CourseOffering>
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
            'verification_token' => null,
            'verification_expires_at' => null,
        ];
    }

    public function withVerificationToken(int $days = 3): static
    {
        return $this->state(fn () => [
            'verification_token' => Str::random(64),
            'verification_expires_at' => now()->addDays($days),
        ]);
    }
}
