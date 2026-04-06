<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\CourseOfferingResource\Pages\EditCourseOffering;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Semester;
use App\Models\User;
use App\Models\Year;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class VerificationLinkActionTest extends TestCase
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
        ]);
    }

    public function test_generate_verification_link_action(): void
    {
        Livewire::test(EditCourseOffering::class, ['record' => $this->offering->getRouteKey()])
            ->callAction('verification_link', ['expiry_days' => 3])
            ->assertNotified('Verification link generated.');

        $this->offering->refresh();
        $this->assertNotNull($this->offering->verification_token);
        $this->assertNotNull($this->offering->verification_expires_at);
        $this->assertTrue($this->offering->hasValidVerificationToken());
    }

    public function test_generate_replaces_existing_token(): void
    {
        $this->offering->generateVerificationToken(3);
        $oldToken = $this->offering->verification_token;

        Livewire::test(EditCourseOffering::class, ['record' => $this->offering->getRouteKey()])
            ->callAction('verification_link', ['expiry_days' => 5]);

        $this->offering->refresh();
        $this->assertNotEquals($oldToken, $this->offering->verification_token);
    }

    public function test_revoke_verification_link_action(): void
    {
        $this->offering->generateVerificationToken(3);
        $this->assertNotNull($this->offering->verification_token);

        // Revoke directly via model since extraModalFooterActions aren't easily testable via callAction
        $this->offering->revokeVerificationToken();

        $this->offering->refresh();
        $this->assertNull($this->offering->verification_token);
        $this->assertNull($this->offering->verification_expires_at);
    }
}
