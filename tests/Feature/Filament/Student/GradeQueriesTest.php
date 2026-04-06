<?php

namespace Tests\Feature\Filament\Student;

use App\Filament\Student\Pages\GradeQueries;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\GradeQuery;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use App\Models\Year;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class GradeQueriesTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Student $student;

    private Enrollment $enrollment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->student()->create();
        $this->student = Student::factory()->create(['email' => $this->user->email]);
        $this->actingAs($this->user);

        $year = Year::factory()->create();
        $semester = Semester::factory()->create(['year_id' => $year->id]);
        $course = Course::factory()->create(['year_id' => $year->id]);
        $offering = CourseOffering::factory()->create([
            'course_id' => $course->id,
            'semester_id' => $semester->id,
        ]);

        $this->enrollment = Enrollment::factory()->create([
            'student_id' => $this->student->id,
            'course_offering_id' => $offering->id,
        ]);
    }

    public function test_grade_queries_page_renders(): void
    {
        Livewire::test(GradeQueries::class)
            ->assertSuccessful();
    }

    public function test_can_toggle_create_form(): void
    {
        Livewire::test(GradeQueries::class)
            ->assertSet('showCreateForm', false)
            ->call('toggleCreateForm')
            ->assertSet('showCreateForm', true);
    }

    public function test_can_submit_grade_query(): void
    {
        Livewire::test(GradeQueries::class)
            ->set('selectedEnrollmentId', $this->enrollment->id)
            ->set('querySubject', 'Test Query')
            ->set('queryBody', 'I have a question about my grade.')
            ->call('submitQuery');

        $this->assertDatabaseHas('grade_queries', [
            'student_id' => $this->student->id,
            'enrollment_id' => $this->enrollment->id,
            'subject' => 'Test Query',
            'status' => 'open',
        ]);
    }

    public function test_shows_existing_queries(): void
    {
        GradeQuery::factory()->create([
            'student_id' => $this->student->id,
            'enrollment_id' => $this->enrollment->id,
            'subject' => 'My Grade Query',
            'status' => 'open',
            'student_message' => 'Please review my grade.',
        ]);

        Livewire::test(GradeQueries::class)
            ->assertSee('My Grade Query');
    }

    public function test_can_reply_to_query(): void
    {
        $query = GradeQuery::factory()->create([
            'student_id' => $this->student->id,
            'enrollment_id' => $this->enrollment->id,
            'subject' => 'Test',
            'status' => 'open',
            'student_message' => 'Initial message.',
        ]);

        Livewire::test(GradeQueries::class)
            ->call('startReply', $query->id)
            ->assertSet('replyingToQueryId', $query->id)
            ->set('replyBody', 'Follow-up message.')
            ->call('submitReply');

        $this->assertDatabaseHas('grade_query_messages', [
            'grade_query_id' => $query->id,
            'user_id' => $this->user->id,
            'body' => 'Follow-up message.',
        ]);
    }

    public function test_cannot_reply_to_another_students_query(): void
    {
        $otherStudent = Student::factory()->create();
        $otherEnrollment = Enrollment::factory()->create([
            'student_id' => $otherStudent->id,
            'course_offering_id' => $this->enrollment->course_offering_id,
        ]);

        $query = GradeQuery::factory()->create([
            'student_id' => $otherStudent->id,
            'enrollment_id' => $otherEnrollment->id,
            'subject' => 'Other Query',
            'status' => 'open',
            'student_message' => 'Not my query.',
        ]);

        Livewire::test(GradeQueries::class)
            ->set('replyingToQueryId', $query->id)
            ->set('replyBody', 'Trying to reply to someone else.')
            ->call('submitReply');

        $this->assertDatabaseMissing('grade_query_messages', [
            'grade_query_id' => $query->id,
            'user_id' => $this->user->id,
        ]);
    }

    public function test_cannot_submit_query_for_another_students_enrollment(): void
    {
        $otherStudent = Student::factory()->create();
        $otherEnrollment = Enrollment::factory()->create([
            'student_id' => $otherStudent->id,
            'course_offering_id' => $this->enrollment->course_offering_id,
        ]);

        Livewire::test(GradeQueries::class)
            ->set('selectedEnrollmentId', $otherEnrollment->id)
            ->set('querySubject', 'Sneaky Query')
            ->set('queryBody', 'Trying to submit for another student.')
            ->call('submitQuery');

        $this->assertDatabaseMissing('grade_queries', [
            'student_id' => $this->student->id,
            'enrollment_id' => $otherEnrollment->id,
        ]);
    }
}
