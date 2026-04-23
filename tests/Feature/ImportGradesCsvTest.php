<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\AssessmentGroup;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImportGradesCsvTest extends TestCase
{
    use RefreshDatabase;

    public function test_imports_grades_from_base64_csv(): void
    {
        [$offering, $assessment, $student] = $this->createOfferingWithStudent();

        $csv = "student_id,assessment_name,raw_score\n{$student->student_id_number},{$assessment->name},27.5\n";
        $base64 = base64_encode($csv);

        $this->artisan("app:import-grades-csv {$offering->id} --base64={$base64}")->assertSuccessful();

        $this->assertDatabaseHas('grade_results', [
            'assessment_id' => $assessment->id,
            'raw_score' => 27.5,
        ]);
    }

    public function test_imports_grades_from_file(): void
    {
        [$offering, $assessment, $student] = $this->createOfferingWithStudent();

        $path = tempnam(sys_get_temp_dir(), 'grades_').'.csv';
        file_put_contents($path, "student_id,assessment_name,raw_score\n{$student->student_id_number},{$assessment->name},80\n");

        $this->artisan("app:import-grades-csv {$offering->id} --file={$path}")->assertSuccessful();

        $this->assertDatabaseHas('grade_results', [
            'assessment_id' => $assessment->id,
            'raw_score' => 80,
        ]);

        @unlink($path);
    }

    public function test_fails_when_no_file_or_base64_provided(): void
    {
        [$offering] = $this->createOfferingWithStudent();

        $this->artisan("app:import-grades-csv {$offering->id}")->assertFailed();
    }

    public function test_fails_when_offering_missing(): void
    {
        $this->artisan('app:import-grades-csv 9999999 --file=/tmp/nonexistent.csv')->assertFailed();
    }

    /**
     * @return array{CourseOffering, Assessment, Student}
     */
    private function createOfferingWithStudent(): array
    {
        $course = Course::factory()->create();
        $offering = CourseOffering::factory()->create(['course_id' => $course->id]);
        $group = AssessmentGroup::factory()->create(['course_offering_id' => $offering->id]);
        $assessment = Assessment::factory()->create([
            'assessment_group_id' => $group->id,
            'course_id' => $course->id,
        ]);
        $student = Student::factory()->create();
        Enrollment::factory()->create([
            'student_id' => $student->id,
            'course_offering_id' => $offering->id,
        ]);

        return [$offering, $assessment, $student];
    }
}
