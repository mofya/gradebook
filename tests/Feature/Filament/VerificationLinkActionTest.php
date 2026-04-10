<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\CourseOfferingResource\Pages\ManageLinks;
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

    public function test_manage_links_page_loads(): void
    {
        Livewire::test(ManageLinks::class, ['record' => $this->offering->getRouteKey()])
            ->assertOk()
            ->assertSee('Student Verification Link')
            ->assertSee('Public Class Grade Sheet');
    }

    public function test_generate_verification_link(): void
    {
        Livewire::test(ManageLinks::class, ['record' => $this->offering->getRouteKey()])
            ->call('generateVerificationLink', 3)
            ->assertNotified('Verification link generated.');

        $this->offering->refresh();
        $this->assertNotNull($this->offering->verification_token);
        $this->assertTrue($this->offering->hasValidVerificationToken());
    }

    public function test_extend_verification_link(): void
    {
        $this->offering->generateVerificationToken(1);
        $originalExpiry = $this->offering->verification_expires_at;

        Livewire::test(ManageLinks::class, ['record' => $this->offering->getRouteKey()])
            ->call('extendVerificationLink', 14)
            ->assertNotified('Verification link expiry extended.');

        $this->offering->refresh();
        $this->assertTrue($this->offering->verification_expires_at->isAfter($originalExpiry));
    }

    public function test_revoke_verification_link(): void
    {
        $this->offering->generateVerificationToken(3);

        Livewire::test(ManageLinks::class, ['record' => $this->offering->getRouteKey()])
            ->call('revokeVerificationLink')
            ->assertNotified('Verification link revoked.');

        $this->offering->refresh();
        $this->assertNull($this->offering->verification_token);
        $this->assertFalse($this->offering->hasValidVerificationToken());
    }

    public function test_generate_public_grade_link(): void
    {
        Livewire::test(ManageLinks::class, ['record' => $this->offering->getRouteKey()])
            ->call('generatePublicGradeLink', 7)
            ->assertNotified('Public grade sheet link generated.');

        $this->offering->refresh();
        $this->assertNotNull($this->offering->public_grade_token);
        $this->assertTrue($this->offering->hasValidPublicGradeToken());
    }

    public function test_extend_public_grade_link(): void
    {
        $this->offering->generatePublicGradeToken(1);
        $originalExpiry = $this->offering->public_grade_token_expires_at;

        Livewire::test(ManageLinks::class, ['record' => $this->offering->getRouteKey()])
            ->call('extendPublicGradeLink', 30)
            ->assertNotified('Public grade sheet expiry extended.');

        $this->offering->refresh();
        $this->assertTrue($this->offering->public_grade_token_expires_at->isAfter($originalExpiry));
    }

    public function test_revoke_public_grade_link(): void
    {
        $this->offering->generatePublicGradeToken(7);

        Livewire::test(ManageLinks::class, ['record' => $this->offering->getRouteKey()])
            ->call('revokePublicGradeLink')
            ->assertNotified('Public grade sheet link revoked.');

        $this->offering->refresh();
        $this->assertNull($this->offering->public_grade_token);
        $this->assertFalse($this->offering->hasValidPublicGradeToken());
    }

    public function test_shows_active_link_urls_when_generated(): void
    {
        $this->offering->generateVerificationToken(3);
        $this->offering->generatePublicGradeToken(7);

        Livewire::test(ManageLinks::class, ['record' => $this->offering->getRouteKey()])
            ->assertSee('Active')
            ->assertSee($this->offering->verification_token)
            ->assertSee($this->offering->public_grade_token);
    }
}
