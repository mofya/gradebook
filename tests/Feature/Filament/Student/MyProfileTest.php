<?php

namespace Tests\Feature\Filament\Student;

use App\Filament\Student\Pages\MyProfile;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class MyProfileTest extends TestCase
{
    use RefreshDatabase;

    private function actAsRegisteredStudent(array $studentOverrides = []): array
    {
        $student = Student::factory()->create(array_merge([
            'email' => 'student@unza.zm',
            'personal_email' => 'student@gmail.com',
            'password' => 'password123',
            'registered_at' => now(),
            'github_username' => 'oldgithub',
        ], $studentOverrides));

        $user = User::factory()->student()->create([
            'email' => 'student@gmail.com',
            'password' => 'password123',
        ]);

        $this->actingAs($user);

        return [$student, $user];
    }

    public function test_profile_page_renders_for_authenticated_student(): void
    {
        $this->actAsRegisteredStudent();

        $this->get('/student/my-profile')
            ->assertSuccessful();
    }

    public function test_profile_page_requires_authentication(): void
    {
        $this->get('/student/my-profile')
            ->assertRedirect();
    }

    public function test_update_personal_email(): void
    {
        [$student, $user] = $this->actAsRegisteredStudent();

        Livewire::test(MyProfile::class)
            ->set('profileData.personal_email', 'newemail@gmail.com')
            ->call('updateProfile')
            ->assertHasNoErrors()
            ->assertNotified();

        $student->refresh();
        $user->refresh();

        $this->assertEquals('newemail@gmail.com', $student->personal_email);
        $this->assertEquals('newemail@gmail.com', $user->email);
    }

    public function test_update_email_fails_with_duplicate(): void
    {
        $this->actAsRegisteredStudent();

        // Create another student with the target email
        Student::factory()->create(['personal_email' => 'taken@gmail.com']);

        Livewire::test(MyProfile::class)
            ->set('profileData.personal_email', 'taken@gmail.com')
            ->call('updateProfile')
            ->assertHasErrors(['profileData.personal_email']);
    }

    public function test_update_email_fails_with_institutional_email(): void
    {
        $this->actAsRegisteredStudent();

        // Another student's institutional email
        Student::factory()->create(['email' => 'other@unza.zm']);

        Livewire::test(MyProfile::class)
            ->set('profileData.personal_email', 'other@unza.zm')
            ->call('updateProfile')
            ->assertHasErrors(['profileData.personal_email']);
    }

    public function test_update_password(): void
    {
        [$student, $user] = $this->actAsRegisteredStudent();

        Livewire::test(MyProfile::class)
            ->set('passwordData.current_password', 'password123')
            ->set('passwordData.new_password', 'newsecretpass')
            ->set('passwordData.new_password_confirmation', 'newsecretpass')
            ->call('updatePassword')
            ->assertHasNoErrors()
            ->assertNotified();

        $student->refresh();
        $this->assertTrue(Hash::check('newsecretpass', $student->password));
    }

    public function test_update_password_fails_with_wrong_current(): void
    {
        $this->actAsRegisteredStudent();

        Livewire::test(MyProfile::class)
            ->set('passwordData.current_password', 'wrongpassword')
            ->set('passwordData.new_password', 'newsecretpass')
            ->set('passwordData.new_password_confirmation', 'newsecretpass')
            ->call('updatePassword')
            ->assertHasErrors(['passwordData.current_password']);
    }

    public function test_update_github_username(): void
    {
        Http::fake([
            'api.github.com/users/newgithub' => Http::response(['login' => 'newgithub'], 200),
        ]);

        [$student] = $this->actAsRegisteredStudent();

        Livewire::test(MyProfile::class)
            ->set('githubData.github_username', 'newgithub')
            ->call('updateGithub')
            ->assertHasNoErrors()
            ->assertNotified();

        $student->refresh();
        $this->assertEquals('newgithub', $student->github_username);
    }

    public function test_update_github_fails_with_nonexistent_username(): void
    {
        Http::fake([
            'api.github.com/users/fakegithub' => Http::response(null, 404),
        ]);

        $this->actAsRegisteredStudent();

        Livewire::test(MyProfile::class)
            ->set('githubData.github_username', 'fakegithub')
            ->call('updateGithub')
            ->assertHasErrors(['githubData.github_username']);
    }

    public function test_update_github_fails_with_taken_username(): void
    {
        Http::fake([
            'api.github.com/users/takengithub' => Http::response(['login' => 'takengithub'], 200),
        ]);

        // Another student with this github
        Student::factory()->create(['github_username' => 'takengithub']);

        $this->actAsRegisteredStudent();

        Livewire::test(MyProfile::class)
            ->set('githubData.github_username', 'takengithub')
            ->call('updateGithub')
            ->assertHasErrors(['githubData.github_username']);
    }

    public function test_clear_github_username(): void
    {
        Http::fake();

        [$student] = $this->actAsRegisteredStudent();

        Livewire::test(MyProfile::class)
            ->set('githubData.github_username', '')
            ->call('updateGithub')
            ->assertHasNoErrors()
            ->assertNotified();

        $student->refresh();
        $this->assertNull($student->github_username);
    }

    public function test_update_gender(): void
    {
        [$student] = $this->actAsRegisteredStudent(['gender' => null]);

        Livewire::test(MyProfile::class)
            ->set('genderData.gender', 'Female')
            ->call('updateGender')
            ->assertHasNoErrors()
            ->assertNotified();

        $student->refresh();
        $this->assertEquals('Female', $student->gender);
    }

    public function test_clear_gender(): void
    {
        [$student] = $this->actAsRegisteredStudent(['gender' => 'Male']);

        Livewire::test(MyProfile::class)
            ->set('genderData.gender', '')
            ->call('updateGender')
            ->assertHasNoErrors()
            ->assertNotified();

        $student->refresh();
        $this->assertNull($student->gender);
    }

    public function test_update_gender_rejects_invalid_value(): void
    {
        [$student] = $this->actAsRegisteredStudent(['gender' => 'Male']);

        Livewire::test(MyProfile::class)
            ->set('genderData.gender', 'InvalidValue')
            ->call('updateGender')
            ->assertHasErrors(['genderData.gender']);

        $student->refresh();
        $this->assertEquals('Male', $student->gender);
    }
}
