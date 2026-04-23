<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\AssessmentGroup;
use App\Models\Course;
use App\Models\CourseOffering;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SetupOfferingAssessmentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_groups_and_assessments_from_spec(): void
    {
        $course = Course::factory()->create();
        $offering = CourseOffering::factory()->create(['course_id' => $course->id]);

        $spec = [
            'groups' => [
                [
                    'name' => 'Quizzes',
                    'type' => 'ca',
                    'weight_percentage' => 10,
                    'sort_order' => 3,
                    'assessments' => [
                        ['name' => 'Quiz 1', 'max_raw_score' => 100, 'normalized_to' => 100, 'has_subsections' => false],
                        ['name' => 'Quiz 2', 'max_raw_score' => 100, 'normalized_to' => 100, 'has_subsections' => false],
                    ],
                ],
            ],
        ];
        $b64 = base64_encode(json_encode($spec));

        $this->artisan("app:setup-offering-assessments {$offering->id} --spec-base64={$b64}")->assertSuccessful();

        $group = AssessmentGroup::where('course_offering_id', $offering->id)->where('name', 'Quizzes')->firstOrFail();
        $this->assertEquals('10.00', $group->weight_percentage);
        $this->assertEquals(2, $group->assessments()->count());
        $this->assertEquals('100.00', $group->assessments()->first()->max_raw_score);
    }

    public function test_is_idempotent_on_repeat_run(): void
    {
        $course = Course::factory()->create();
        $offering = CourseOffering::factory()->create(['course_id' => $course->id]);

        $spec = [
            'groups' => [
                [
                    'name' => 'Quizzes',
                    'weight_percentage' => 10,
                    'assessments' => [
                        ['name' => 'Quiz 1', 'max_raw_score' => 100],
                    ],
                ],
            ],
        ];
        $b64 = base64_encode(json_encode($spec));

        $this->artisan("app:setup-offering-assessments {$offering->id} --spec-base64={$b64}")->assertSuccessful();
        $firstCount = Assessment::count();

        $this->artisan("app:setup-offering-assessments {$offering->id} --spec-base64={$b64}")->assertSuccessful();
        $this->assertEquals($firstCount, Assessment::count());
    }

    public function test_fails_without_spec(): void
    {
        $course = Course::factory()->create();
        $offering = CourseOffering::factory()->create(['course_id' => $course->id]);

        $this->artisan("app:setup-offering-assessments {$offering->id}")->assertFailed();
    }

    public function test_fails_when_offering_missing(): void
    {
        $b64 = base64_encode('{"groups":[]}');
        $this->artisan("app:setup-offering-assessments 9999999 --spec-base64={$b64}")->assertFailed();
    }

    public function test_reads_spec_from_file(): void
    {
        $course = Course::factory()->create();
        $offering = CourseOffering::factory()->create(['course_id' => $course->id]);

        $path = tempnam(sys_get_temp_dir(), 'spec_').'.json';
        file_put_contents($path, json_encode([
            'groups' => [
                ['name' => 'Quizzes', 'weight_percentage' => 10, 'assessments' => [
                    ['name' => 'Quiz 1', 'max_raw_score' => 100],
                ]],
            ],
        ]));

        $this->artisan("app:setup-offering-assessments {$offering->id} --spec-file={$path}")->assertSuccessful();

        $this->assertDatabaseHas('assessments', ['name' => 'Quiz 1', 'max_raw_score' => 100]);

        @unlink($path);
    }
}
