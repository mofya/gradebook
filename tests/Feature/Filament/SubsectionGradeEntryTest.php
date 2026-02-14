<?php

namespace Tests\Feature\Filament;

use App\Models\Assessment;
use App\Models\AssessmentGroup;
use App\Models\AssessmentSubsection;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\GradeResult;
use App\Models\Semester;
use App\Models\Student;
use App\Models\SubsectionScore;
use App\Models\User;
use App\Models\Year;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubsectionGradeEntryTest extends TestCase
{
    use RefreshDatabase;

    private CourseOffering $offering;

    private Assessment $examAssessment;

    private Enrollment $enrollment;

    /** @var \Illuminate\Database\Eloquent\Collection<int, AssessmentSubsection> */
    private $subsections;

    protected function setUp(): void
    {
        parent::setUp();

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

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
            'has_subsections' => true,
        ]);

        $this->subsections = collect([
            AssessmentSubsection::factory()->create([
                'assessment_id' => $this->examAssessment->id,
                'name' => 'Q1',
                'max_score' => 30,
                'sort_order' => 0,
            ]),
            AssessmentSubsection::factory()->create([
                'assessment_id' => $this->examAssessment->id,
                'name' => 'Q2',
                'max_score' => 40,
                'sort_order' => 1,
            ]),
            AssessmentSubsection::factory()->create([
                'assessment_id' => $this->examAssessment->id,
                'name' => 'Q3',
                'max_score' => 30,
                'sort_order' => 2,
            ]),
        ]);

        $student = Student::factory()->create();
        $this->enrollment = Enrollment::factory()->create([
            'student_id' => $student->id,
            'course_offering_id' => $this->offering->id,
        ]);
    }

    public function test_subsection_score_model_relationships(): void
    {
        $result = GradeResult::factory()->create([
            'enrollment_id' => $this->enrollment->id,
            'assessment_id' => $this->examAssessment->id,
            'raw_score' => 70,
        ]);

        $score = SubsectionScore::create([
            'grade_result_id' => $result->id,
            'assessment_subsection_id' => $this->subsections[0]->id,
            'score' => 25,
        ]);

        $this->assertNotNull($score->gradeResult);
        $this->assertNotNull($score->assessmentSubsection);
        $this->assertEquals($result->id, $score->gradeResult->id);
    }

    public function test_grade_result_has_subsection_scores_relationship(): void
    {
        $result = GradeResult::factory()->create([
            'enrollment_id' => $this->enrollment->id,
            'assessment_id' => $this->examAssessment->id,
        ]);

        SubsectionScore::create([
            'grade_result_id' => $result->id,
            'assessment_subsection_id' => $this->subsections[0]->id,
            'score' => 20,
        ]);

        SubsectionScore::create([
            'grade_result_id' => $result->id,
            'assessment_subsection_id' => $this->subsections[1]->id,
            'score' => 35,
        ]);

        $this->assertCount(2, $result->subsectionScores);
    }

    public function test_calculate_from_subsections_sums_scores(): void
    {
        $result = GradeResult::factory()->create([
            'enrollment_id' => $this->enrollment->id,
            'assessment_id' => $this->examAssessment->id,
            'raw_score' => 0,
        ]);

        SubsectionScore::create([
            'grade_result_id' => $result->id,
            'assessment_subsection_id' => $this->subsections[0]->id,
            'score' => 25,
        ]);

        SubsectionScore::create([
            'grade_result_id' => $result->id,
            'assessment_subsection_id' => $this->subsections[1]->id,
            'score' => 35,
        ]);

        SubsectionScore::create([
            'grade_result_id' => $result->id,
            'assessment_subsection_id' => $this->subsections[2]->id,
            'score' => 20,
        ]);

        $total = $result->calculateFromSubsections();

        $this->assertEquals(80, $total);
        $this->assertEquals('80.00', $result->fresh()->raw_score);
    }

    public function test_subsection_score_unique_constraint(): void
    {
        $result = GradeResult::factory()->create([
            'enrollment_id' => $this->enrollment->id,
            'assessment_id' => $this->examAssessment->id,
        ]);

        SubsectionScore::create([
            'grade_result_id' => $result->id,
            'assessment_subsection_id' => $this->subsections[0]->id,
            'score' => 20,
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        SubsectionScore::create([
            'grade_result_id' => $result->id,
            'assessment_subsection_id' => $this->subsections[0]->id,
            'score' => 25,
        ]);
    }

    public function test_assessment_has_subsections_flag(): void
    {
        $this->assertTrue($this->examAssessment->has_subsections);
        $this->assertCount(3, $this->examAssessment->subsections);
    }
}
