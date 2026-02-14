<?php

namespace Tests\Feature\Filament;

use App\Filament\Pages\ClassReport;
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
use Tests\TestCase;

class ClassReportEnhancedTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private CourseOffering $offering;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->actingAs($this->admin);

        $year = Year::factory()->create();
        $semester = Semester::factory()->create(['year_id' => $year->id]);
        $course = Course::factory()->create(['year_id' => $year->id]);

        $this->offering = CourseOffering::factory()->create([
            'course_id' => $course->id,
            'semester_id' => $semester->id,
            'lecturer_id' => $this->admin->id,
        ]);
    }

    public function test_class_report_generates_report_data(): void
    {
        $student = Student::factory()->create();
        Enrollment::factory()->create([
            'student_id' => $student->id,
            'course_offering_id' => $this->offering->id,
            'final_total' => 78,
            'final_grade' => 'B+',
            'grade_points' => 3.5,
        ]);

        $page = new ClassReport;
        $page->course_offering_id = $this->offering->id;
        $page->generateReport();

        $this->assertNotNull($page->reportData);
        $this->assertEquals($this->offering->course->code, $page->reportData['course_code']);
        $this->assertCount(1, $page->reportData['students']);
        $this->assertEquals('B+', $page->reportData['students'][0]['final_grade']);
        $this->assertEquals(3.5, $page->reportData['students'][0]['grade_points']);
    }

    public function test_class_report_includes_assessment_stats(): void
    {
        $group = AssessmentGroup::factory()->create([
            'course_offering_id' => $this->offering->id,
            'type' => 'ca',
        ]);

        $assessment = Assessment::factory()->create([
            'assessment_group_id' => $group->id,
            'course_id' => $this->offering->course_id,
            'name' => 'Midterm Quiz',
            'max_raw_score' => 30,
        ]);

        $student = Student::factory()->create();
        $enrollment = Enrollment::factory()->create([
            'student_id' => $student->id,
            'course_offering_id' => $this->offering->id,
            'final_total' => 65,
            'final_grade' => 'C+',
        ]);

        GradeResult::factory()->create([
            'enrollment_id' => $enrollment->id,
            'assessment_id' => $assessment->id,
            'raw_score' => 22,
            'is_excused' => false,
        ]);

        $page = new ClassReport;
        $page->course_offering_id = $this->offering->id;
        $page->generateReport();

        $this->assertNotNull($page->reportData['assessment_stats']);
        $this->assertCount(1, $page->reportData['assessment_stats']);
        $this->assertEquals('Midterm Quiz', $page->reportData['assessment_stats'][0]['assessment_name']);
    }

    public function test_class_report_calculates_statistics(): void
    {
        $student1 = Student::factory()->create();
        Enrollment::factory()->create([
            'student_id' => $student1->id,
            'course_offering_id' => $this->offering->id,
            'final_total' => 90,
            'final_grade' => 'A+',
        ]);

        $student2 = Student::factory()->create();
        Enrollment::factory()->create([
            'student_id' => $student2->id,
            'course_offering_id' => $this->offering->id,
            'final_total' => 40,
            'final_grade' => 'C',
        ]);

        $page = new ClassReport;
        $page->course_offering_id = $this->offering->id;
        $page->generateReport();

        $stats = $page->reportData['stats'];
        $this->assertEquals(2, $stats['total_enrolled']);
        $this->assertEquals(65, $stats['average']);
        $this->assertEquals(90, $stats['highest']);
        $this->assertEquals(40, $stats['lowest']);
        $this->assertEquals(100, $stats['pass_rate']);
    }
}
