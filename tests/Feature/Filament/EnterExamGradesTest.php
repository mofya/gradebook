<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\CourseOfferingResource\Pages\EnterExamGrades;
use App\Models\Assessment;
use App\Models\AssessmentGroup;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\GradeResult;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use App\Models\Year;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EnterExamGradesTest extends TestCase
{
    use RefreshDatabase;

    private CourseOffering $offering;

    private Assessment $examAssessment;

    private Enrollment $enrollment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->admin()->create());

        $year = Year::factory()->create();
        $semester = Semester::factory()->create(['year_id' => $year->id]);
        $course = Course::factory()->create(['year_id' => $year->id]);

        $this->offering = CourseOffering::factory()->create([
            'course_id' => $course->id,
            'semester_id' => $semester->id,
        ]);

        $examGroup = AssessmentGroup::factory()->create([
            'course_offering_id' => $this->offering->id,
            'type' => 'exam',
        ]);

        $this->examAssessment = Assessment::factory()->create([
            'assessment_group_id' => $examGroup->id,
            'course_id' => $course->id,
            'name' => 'Final Exam',
            'max_raw_score' => 100,
        ]);

        $student = Student::factory()->create();
        $this->enrollment = Enrollment::factory()->create([
            'student_id' => $student->id,
            'course_offering_id' => $this->offering->id,
        ]);
    }

    public function test_page_renders(): void
    {
        $this->get(EnterExamGrades::getUrl(['record' => $this->offering]))
            ->assertSuccessful();
    }

    public function test_can_load_grades_for_exam_assessment(): void
    {
        Livewire::test(EnterExamGrades::class, ['record' => $this->offering->getRouteKey()])
            ->set('selectedAssessmentId', $this->examAssessment->id)
            ->assertSet("grades.{$this->enrollment->id}.student_name", $this->enrollment->student->first_name.' '.$this->enrollment->student->last_name);
    }

    public function test_can_submit_exam_grades(): void
    {
        Livewire::test(EnterExamGrades::class, ['record' => $this->offering->getRouteKey()])
            ->set('selectedAssessmentId', $this->examAssessment->id)
            ->set("grades.{$this->enrollment->id}.raw_score", 85)
            ->call('submit');

        $this->assertDatabaseHas('grade_results', [
            'enrollment_id' => $this->enrollment->id,
            'assessment_id' => $this->examAssessment->id,
            'raw_score' => 85,
        ]);
    }

    public function test_can_update_existing_exam_grades(): void
    {
        GradeResult::factory()->create([
            'enrollment_id' => $this->enrollment->id,
            'assessment_id' => $this->examAssessment->id,
            'raw_score' => 60,
        ]);

        Livewire::test(EnterExamGrades::class, ['record' => $this->offering->getRouteKey()])
            ->set('selectedAssessmentId', $this->examAssessment->id)
            ->set("grades.{$this->enrollment->id}.raw_score", 95)
            ->call('submit');

        $this->assertDatabaseHas('grade_results', [
            'enrollment_id' => $this->enrollment->id,
            'assessment_id' => $this->examAssessment->id,
            'raw_score' => 95,
        ]);

        $this->assertEquals(
            1,
            GradeResult::where('enrollment_id', $this->enrollment->id)
                ->where('assessment_id', $this->examAssessment->id)
                ->count()
        );
    }
}
