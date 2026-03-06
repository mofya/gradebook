<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\StudentResource\Pages\CreateStudent;
use App\Filament\Resources\StudentResource\Pages\EditStudent;
use App\Filament\Resources\StudentResource\Pages\ListStudents;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class StudentResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->admin()->create());
    }

    public function test_list_page_renders(): void
    {
        $this->get(ListStudents::getUrl())->assertSuccessful();
    }

    public function test_can_list_students(): void
    {
        $students = Student::factory()->count(3)->create();

        Livewire::test(ListStudents::class)
            ->assertCanSeeTableRecords($students);
    }

    public function test_create_page_renders(): void
    {
        $this->get(CreateStudent::getUrl())->assertSuccessful();
    }

    public function test_can_create_student(): void
    {
        Livewire::test(CreateStudent::class)
            ->fillForm([
                'first_name' => 'John',
                'last_name' => 'Doe',
                'email' => 'john@example.com',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('students', ['email' => 'john@example.com']);
    }

    public function test_first_name_is_required(): void
    {
        Livewire::test(CreateStudent::class)
            ->fillForm([
                'first_name' => '',
            ])
            ->call('create')
            ->assertHasFormErrors(['first_name' => 'required']);
    }

    public function test_last_name_is_required(): void
    {
        Livewire::test(CreateStudent::class)
            ->fillForm([
                'last_name' => '',
            ])
            ->call('create')
            ->assertHasFormErrors(['last_name' => 'required']);
    }

    public function test_email_is_required(): void
    {
        Livewire::test(CreateStudent::class)
            ->fillForm([
                'email' => '',
            ])
            ->call('create')
            ->assertHasFormErrors(['email' => 'required']);
    }

    public function test_email_must_be_unique(): void
    {
        Student::factory()->create(['email' => 'john@example.com']);

        Livewire::test(CreateStudent::class)
            ->fillForm([
                'first_name' => 'Jane',
                'last_name' => 'Doe',
                'email' => 'john@example.com',
            ])
            ->call('create')
            ->assertHasFormErrors(['email' => 'unique']);
    }

    public function test_edit_page_renders(): void
    {
        $student = Student::factory()->create();

        $this->get(EditStudent::getUrl(['record' => $student]))->assertSuccessful();
    }

    public function test_can_edit_student(): void
    {
        $student = Student::factory()->create();

        Livewire::test(EditStudent::class, ['record' => $student->getRouteKey()])
            ->fillForm([
                'first_name' => 'Updated',
                'last_name' => 'Name',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('students', ['id' => $student->id, 'first_name' => 'Updated']);
    }

    public function test_can_delete_student(): void
    {
        $student = Student::factory()->create();

        Livewire::test(ListStudents::class)
            ->callTableAction('delete', $student);

        $this->assertDatabaseMissing('students', ['id' => $student->id]);
    }
}
