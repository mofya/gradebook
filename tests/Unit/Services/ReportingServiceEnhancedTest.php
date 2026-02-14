<?php

namespace Tests\Unit\Services;

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
use App\Services\ReportingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportingServiceEnhancedTest extends TestCase
{
    use RefreshDatabase;

    private ReportingService $reportingService;

    private CourseOffering $offering;

    protected function setUp(): void
    {
        parent::setUp();

        $this->reportingService = app(ReportingService::class);

        $year = Year::factory()->create();
        $semester = Semester::factory()->create(['year_id' => $year->id]);
        $course = Course::factory()->create(['year_id' => $year->id]);
        $lecturer = User::factory()->lecturer()->create();

        $this->offering = CourseOffering::factory()->create([
            'course_id' => $course->id,
            'semester_id' => $semester->id,
            'lecturer_id' => $lecturer->id,
            'ca_weight' => 40,
            'exam_weight' => 60,
        ]);
    }

    public function test_generate_offering_report_includes_assessment_stats(): void
    {
        $group = AssessmentGroup::factory()->create([
            'course_offering_id' => $this->offering->id,
            'type' => 'ca',
        ]);

        $assessment = Assessment::factory()->create([
            'assessment_group_id' => $group->id,
            'course_id' => $this->offering->course_id,
            'name' => 'Quiz 1',
            'max_raw_score' => 20,
        ]);

        $student = Student::factory()->create();
        $enrollment = Enrollment::factory()->create([
            'student_id' => $student->id,
            'course_offering_id' => $this->offering->id,
            'final_total' => 75,
            'final_grade' => 'B+',
        ]);

        GradeResult::factory()->create([
            'enrollment_id' => $enrollment->id,
            'assessment_id' => $assessment->id,
            'raw_score' => 15,
            'is_excused' => false,
        ]);

        $report = $this->reportingService->generateOfferingReport($this->offering);

        $this->assertArrayHasKey('assessment_stats', $report);
        $this->assertCount(1, $report['assessment_stats']);
        $this->assertEquals('Quiz 1', $report['assessment_stats'][0]['assessment_name']);
        $this->assertEquals(1, $report['assessment_stats'][0]['count']);
        $this->assertEquals(15, $report['assessment_stats'][0]['average']);
    }

    public function test_assessment_stats_excludes_excused_results(): void
    {
        $group = AssessmentGroup::factory()->create([
            'course_offering_id' => $this->offering->id,
            'type' => 'ca',
        ]);

        $assessment = Assessment::factory()->create([
            'assessment_group_id' => $group->id,
            'course_id' => $this->offering->course_id,
            'name' => 'Assignment 1',
            'max_raw_score' => 50,
        ]);

        $student1 = Student::factory()->create();
        $enrollment1 = Enrollment::factory()->create([
            'student_id' => $student1->id,
            'course_offering_id' => $this->offering->id,
        ]);

        $student2 = Student::factory()->create();
        $enrollment2 = Enrollment::factory()->create([
            'student_id' => $student2->id,
            'course_offering_id' => $this->offering->id,
        ]);

        GradeResult::factory()->create([
            'enrollment_id' => $enrollment1->id,
            'assessment_id' => $assessment->id,
            'raw_score' => 40,
            'is_excused' => false,
        ]);

        GradeResult::factory()->create([
            'enrollment_id' => $enrollment2->id,
            'assessment_id' => $assessment->id,
            'raw_score' => 10,
            'is_excused' => true,
        ]);

        $stats = $this->reportingService->getAssessmentStats($this->offering, $assessment);

        $this->assertEquals(1, $stats['count']);
        $this->assertEquals(40, $stats['average']);
    }

    public function test_assessment_stats_returns_zero_when_no_results(): void
    {
        $group = AssessmentGroup::factory()->create([
            'course_offering_id' => $this->offering->id,
            'type' => 'exam',
        ]);

        $assessment = Assessment::factory()->create([
            'assessment_group_id' => $group->id,
            'course_id' => $this->offering->course_id,
            'name' => 'Final Exam',
            'max_raw_score' => 100,
        ]);

        $stats = $this->reportingService->getAssessmentStats($this->offering, $assessment);

        $this->assertEquals(0, $stats['count']);
        $this->assertEquals(0, $stats['average']);
        $this->assertEquals(0, $stats['highest']);
        $this->assertEquals(0, $stats['lowest']);
        $this->assertEquals(100, $stats['max_raw_score']);
    }

    public function test_offering_report_includes_grade_distribution(): void
    {
        $student1 = Student::factory()->create();
        Enrollment::factory()->create([
            'student_id' => $student1->id,
            'course_offering_id' => $this->offering->id,
            'final_total' => 85,
            'final_grade' => 'A',
        ]);

        $student2 = Student::factory()->create();
        Enrollment::factory()->create([
            'student_id' => $student2->id,
            'course_offering_id' => $this->offering->id,
            'final_total' => 45,
            'final_grade' => 'C',
        ]);

        $report = $this->reportingService->generateOfferingReport($this->offering);

        $this->assertArrayHasKey('distribution', $report);
        $this->assertEquals(1, $report['distribution']['A']);
        $this->assertEquals(1, $report['distribution']['C']);
        $this->assertEquals(0, $report['distribution']['B+']);
    }
}
