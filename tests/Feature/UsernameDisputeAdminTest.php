<?php

namespace Tests\Feature;

use App\Filament\Pages\UsernameDisputes;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use App\Models\UsernameDispute;
use App\Models\Year;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class UsernameDisputeAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $lecturer;

    private User $otherLecturer;

    private CourseOffering $offering;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->lecturer = User::factory()->lecturer()->create();
        $this->otherLecturer = User::factory()->lecturer()->create();

        $year = Year::factory()->create();
        $semester = Semester::factory()->create(['year_id' => $year->id]);
        $course = Course::factory()->create(['year_id' => $year->id]);

        $this->offering = CourseOffering::factory()->create([
            'course_id' => $course->id,
            'semester_id' => $semester->id,
            'lecturer_id' => $this->lecturer->id,
        ]);
    }

    public function test_lecturer_only_sees_disputes_for_their_offerings(): void
    {
        $ownDispute = UsernameDispute::factory()->create([
            'course_offering_id' => $this->offering->id,
        ]);

        $otherOffering = CourseOffering::factory()->create([
            'lecturer_id' => $this->otherLecturer->id,
        ]);
        $otherDispute = UsernameDispute::factory()->create([
            'course_offering_id' => $otherOffering->id,
        ]);

        $this->actingAs($this->lecturer);

        $page = new UsernameDisputes;
        $data = $page->getViewData();

        $this->assertTrue($data['disputes']->contains($ownDispute));
        $this->assertFalse($data['disputes']->contains($otherDispute));
    }

    public function test_admin_sees_all_disputes(): void
    {
        $dispute1 = UsernameDispute::factory()->create([
            'course_offering_id' => $this->offering->id,
        ]);

        $otherOffering = CourseOffering::factory()->create([
            'lecturer_id' => $this->otherLecturer->id,
        ]);
        $dispute2 = UsernameDispute::factory()->create([
            'course_offering_id' => $otherOffering->id,
        ]);

        $this->actingAs($this->admin);

        $page = new UsernameDisputes;
        $data = $page->getViewData();

        $this->assertTrue($data['disputes']->contains($dispute1));
        $this->assertTrue($data['disputes']->contains($dispute2));
    }

    public function test_lecturer_cannot_resolve_dispute_for_other_offering(): void
    {
        $otherOffering = CourseOffering::factory()->create([
            'lecturer_id' => $this->otherLecturer->id,
        ]);
        $dispute = UsernameDispute::factory()->create([
            'course_offering_id' => $otherOffering->id,
        ]);

        $this->actingAs($this->lecturer);

        Livewire::test(UsernameDisputes::class)
            ->call('assignToClaimant', $dispute->id)
            ->assertNotified('You are not authorized to resolve this dispute.');

        $this->assertDatabaseHas('username_disputes', [
            'id' => $dispute->id,
            'status' => 'pending',
        ]);
    }

    public function test_lecturer_cannot_reject_dispute_for_other_offering(): void
    {
        $otherOffering = CourseOffering::factory()->create([
            'lecturer_id' => $this->otherLecturer->id,
        ]);
        $dispute = UsernameDispute::factory()->create([
            'course_offering_id' => $otherOffering->id,
        ]);

        $this->actingAs($this->lecturer);

        Livewire::test(UsernameDisputes::class)
            ->call('keepCurrentHolder', $dispute->id)
            ->assertNotified('You are not authorized to resolve this dispute.');

        $this->assertDatabaseHas('username_disputes', [
            'id' => $dispute->id,
            'status' => 'pending',
        ]);
    }

    public function test_resolving_dispute_closes_sibling_disputes(): void
    {
        $claimant1 = Student::factory()->create();
        $claimant2 = Student::factory()->create();
        $holder = Student::factory()->create(['github_username' => 'contested-user']);

        $dispute1 = UsernameDispute::factory()->create([
            'claimant_student_id' => $claimant1->id,
            'current_holder_student_id' => $holder->id,
            'github_username' => 'contested-user',
            'course_offering_id' => $this->offering->id,
        ]);

        $dispute2 = UsernameDispute::factory()->create([
            'claimant_student_id' => $claimant2->id,
            'current_holder_student_id' => $holder->id,
            'github_username' => 'contested-user',
            'course_offering_id' => $this->offering->id,
        ]);

        $this->actingAs($this->admin);

        Livewire::test(UsernameDisputes::class)
            ->call('assignToClaimant', $dispute1->id);

        $this->assertDatabaseHas('username_disputes', [
            'id' => $dispute1->id,
            'status' => 'resolved',
        ]);

        $this->assertDatabaseHas('username_disputes', [
            'id' => $dispute2->id,
            'status' => 'rejected',
        ]);
    }
}
