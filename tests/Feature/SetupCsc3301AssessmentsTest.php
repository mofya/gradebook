<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\AssessmentGroup;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\GradeResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SetupCsc3301AssessmentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_groups_and_assessments_for_csc3301_offering(): void
    {
        $course = Course::factory()->create(['code' => 'CSC3301']);
        $offering = CourseOffering::factory()->create(['course_id' => $course->id]);

        $this->artisan('app:setup-csc3301-assessments')->assertSuccessful();

        $groupNames = AssessmentGroup::where('course_offering_id', $offering->id)
            ->pluck('weight_percentage', 'name');

        $this->assertEquals('10.00', $groupNames['Labs']);
        $this->assertEquals('10.00', $groupNames['Projects']);
        $this->assertEquals('10.00', $groupNames['Quizzes']);
        $this->assertEquals('10.00', $groupNames['Midterm Test']);

        $quizzes = AssessmentGroup::where('course_offering_id', $offering->id)->where('name', 'Quizzes')->firstOrFail();
        $this->assertEquals(4, $quizzes->assessments()->count());
        $this->assertEquals('30.00', $quizzes->assessments()->first()->max_raw_score);

        $midterm = AssessmentGroup::where('course_offering_id', $offering->id)->where('name', 'Midterm Test')->firstOrFail();
        $this->assertEquals(1, $midterm->assessments()->count());
        $this->assertEquals('100.00', $midterm->assessments()->first()->max_raw_score);

        $labs = AssessmentGroup::where('course_offering_id', $offering->id)->where('name', 'Labs')->firstOrFail();
        $this->assertEquals(4, $labs->assessments()->count());
    }

    public function test_moves_existing_project_assessments_out_of_labs_preserving_grade_results(): void
    {
        $course = Course::factory()->create(['code' => 'CSC3301']);
        $offering = CourseOffering::factory()->create(['course_id' => $course->id]);

        $labs = AssessmentGroup::factory()->create([
            'course_offering_id' => $offering->id,
            'name' => 'Labs',
        ]);

        $project1 = Assessment::factory()->create([
            'assessment_group_id' => $labs->id,
            'course_id' => $course->id,
            'name' => 'Project 01 - Expression Evaluator',
        ]);
        $grade = GradeResult::factory()->create(['assessment_id' => $project1->id]);

        $this->artisan('app:setup-csc3301-assessments')->assertSuccessful();

        $projects = AssessmentGroup::where('course_offering_id', $offering->id)->where('name', 'Projects')->firstOrFail();

        $this->assertEquals($projects->id, $project1->fresh()->assessment_group_id);
        $this->assertDatabaseHas('grade_results', ['id' => $grade->id, 'assessment_id' => $project1->id]);
    }

    public function test_is_idempotent_on_repeat_runs(): void
    {
        $course = Course::factory()->create(['code' => 'CSC3301']);
        CourseOffering::factory()->create(['course_id' => $course->id]);

        $this->artisan('app:setup-csc3301-assessments')->assertSuccessful();
        $firstCount = Assessment::count();

        $this->artisan('app:setup-csc3301-assessments')->assertSuccessful();
        $secondCount = Assessment::count();

        $this->assertEquals($firstCount, $secondCount);
    }
}
