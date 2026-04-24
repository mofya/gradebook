<?php

namespace Tests\Feature;

use App\Livewire\MissedAssessmentAppealForm;
use App\Models\Assessment;
use App\Models\AssessmentGroup;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\GradeResult;
use App\Models\MissedAssessmentAppeal;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class MissedAssessmentAppealFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_expired_token_shows_expired_step(): void
    {
        Livewire::test(MissedAssessmentAppealForm::class, ['token' => 'nonexistent-token'])
            ->assertSet('step', 'expired');
    }

    public function test_student_can_submit_appeal_with_selected_assessments(): void
    {
        Storage::fake('local');

        [$offering, $student, $assessments] = $this->seedOffering();

        Livewire::test(MissedAssessmentAppealForm::class, ['token' => $offering->verification_token])
            ->assertSet('step', 'lookup')
            ->set('studentIdNumber', $student->student_id_number)
            ->call('lookupStudent')
            ->assertSet('step', 'form')
            ->set('selectedAssessmentIds', [$assessments[0]->id, $assessments[1]->id])
            ->set('narrative', 'I was admitted to hospital between 10 and 15 April and could not sit these assessments.')
            ->set('otherNotes', '')
            ->set('deanConfirmed', true)
            ->set('evidenceFile', UploadedFile::fake()->create('letter.pdf', 500, 'application/pdf'))
            ->call('submit')
            ->assertSet('step', 'submitted');

        $appeal = MissedAssessmentAppeal::where('course_offering_id', $offering->id)
            ->where('student_id', $student->id)
            ->firstOrFail();

        $this->assertEquals(MissedAssessmentAppeal::STATUS_PENDING, $appeal->status);
        $this->assertTrue($appeal->dean_confirmed);
        $this->assertNotNull($appeal->evidence_path);
        $this->assertEquals(2, $appeal->items()->count());
    }

    public function test_submit_requires_dean_confirmation(): void
    {
        [$offering, $student, $assessments] = $this->seedOffering();

        Livewire::test(MissedAssessmentAppealForm::class, ['token' => $offering->verification_token])
            ->set('studentIdNumber', $student->student_id_number)
            ->call('lookupStudent')
            ->set('selectedAssessmentIds', [$assessments[0]->id])
            ->set('narrative', 'Long enough reason text here.')
            ->set('deanConfirmed', false)
            ->call('submit')
            ->assertHasErrors(['deanConfirmed']);

        $this->assertEquals(0, MissedAssessmentAppeal::count());
    }

    public function test_submit_requires_at_least_one_selected_assessment(): void
    {
        [$offering, $student] = $this->seedOffering();

        Livewire::test(MissedAssessmentAppealForm::class, ['token' => $offering->verification_token])
            ->set('studentIdNumber', $student->student_id_number)
            ->call('lookupStudent')
            ->set('narrative', 'Narrative long enough.')
            ->set('deanConfirmed', true)
            ->call('submit')
            ->assertHasErrors(['selectedAssessmentIds']);
    }

    public function test_only_ungraded_assessments_are_offered(): void
    {
        [$offering, $student, $assessments] = $this->seedOffering();
        $enrollment = Enrollment::where('student_id', $student->id)->firstOrFail();

        GradeResult::factory()->create([
            'enrollment_id' => $enrollment->id,
            'assessment_id' => $assessments[0]->id,
            'raw_score' => 75,
        ]);

        $component = Livewire::test(MissedAssessmentAppealForm::class, ['token' => $offering->verification_token])
            ->set('studentIdNumber', $student->student_id_number)
            ->call('lookupStudent');

        $ids = collect($component->get('availableAssessments'))->pluck('id')->all();
        $this->assertNotContains($assessments[0]->id, $ids);
        $this->assertContains($assessments[1]->id, $ids);
    }

    public function test_unknown_student_id_shows_friendly_error(): void
    {
        [$offering] = $this->seedOffering();

        Livewire::test(MissedAssessmentAppealForm::class, ['token' => $offering->verification_token])
            ->set('studentIdNumber', 'not-a-real-id')
            ->call('lookupStudent')
            ->assertSet('step', 'lookup');
    }

    /**
     * @return array{CourseOffering, Student, array<int, Assessment>}
     */
    private function seedOffering(): array
    {
        $course = Course::factory()->create();
        $offering = CourseOffering::factory()->create([
            'course_id' => $course->id,
            'verification_token' => 'test-token-'.uniqid(),
            'verification_expires_at' => now()->addDay(),
        ]);
        $group = AssessmentGroup::factory()->create(['course_offering_id' => $offering->id]);
        $a1 = Assessment::factory()->create(['assessment_group_id' => $group->id, 'course_id' => $course->id]);
        $a2 = Assessment::factory()->create(['assessment_group_id' => $group->id, 'course_id' => $course->id]);
        $student = Student::factory()->create(['student_id_number' => 'TEST-'.uniqid()]);
        Enrollment::factory()->create([
            'student_id' => $student->id,
            'course_offering_id' => $offering->id,
        ]);

        return [$offering, $student, [$a1, $a2]];
    }
}
