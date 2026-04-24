<?php

namespace Database\Factories;

use App\Models\CourseOffering;
use App\Models\MissedAssessmentAppeal;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MissedAssessmentAppeal>
 */
class MissedAssessmentAppealFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'course_offering_id' => CourseOffering::factory(),
            'student_id' => Student::factory(),
            'narrative' => $this->faker->paragraph(),
            'other_notes' => null,
            'dean_confirmed' => true,
            'evidence_path' => null,
            'status' => MissedAssessmentAppeal::STATUS_PENDING,
            'submitted_at' => now(),
            'reviewed_by' => null,
            'reviewed_at' => null,
        ];
    }
}
