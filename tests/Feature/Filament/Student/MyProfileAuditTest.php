<?php

namespace Tests\Feature\Filament\Student;

use App\Filament\Student\Pages\MyProfile;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MyProfileAuditTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Student $student;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->student()->create();
        $this->student = Student::factory()->create([
            'email' => $this->user->email,
            'personal_email' => $this->user->email,
            'password' => bcrypt('oldpassword'),
        ]);
        $this->actingAs($this->user);
    }

    public function test_email_update_creates_audit_log(): void
    {
        Livewire::test(MyProfile::class)
            ->set('profileData.personal_email', 'newemail@example.com')
            ->call('updateProfile');

        $this->assertDatabaseHas('grade_audit_logs', [
            'auditable_type' => Student::class,
            'auditable_id' => $this->student->id,
            'action' => 'profile_email_updated',
        ]);
    }

    public function test_password_change_creates_audit_log(): void
    {
        Livewire::test(MyProfile::class)
            ->set('passwordData.current_password', 'oldpassword')
            ->set('passwordData.new_password', 'newsecurepassword')
            ->set('passwordData.new_password_confirmation', 'newsecurepassword')
            ->call('updatePassword');

        $this->assertDatabaseHas('grade_audit_logs', [
            'auditable_type' => Student::class,
            'auditable_id' => $this->student->id,
            'action' => 'profile_password_changed',
        ]);
    }

    public function test_gender_update_creates_audit_log(): void
    {
        Livewire::test(MyProfile::class)
            ->set('genderData.gender', 'Male')
            ->call('updateGender');

        $this->assertDatabaseHas('grade_audit_logs', [
            'auditable_type' => Student::class,
            'auditable_id' => $this->student->id,
            'action' => 'profile_gender_updated',
        ]);
    }
}
