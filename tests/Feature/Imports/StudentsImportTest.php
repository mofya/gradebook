<?php

namespace Tests\Feature\Imports;

use App\Imports\StudentsImport;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentsImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_model_maps_row_to_student_attributes(): void
    {
        $import = new StudentsImport;

        $student = $import->model([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
        ]);

        $this->assertInstanceOf(Student::class, $student);
        $this->assertEquals('John', $student->first_name);
        $this->assertEquals('Doe', $student->last_name);
        $this->assertEquals('john@example.com', $student->email);
    }

    public function test_import_creates_students_in_database(): void
    {
        $import = new StudentsImport;

        $import->model([
            'first_name' => 'Alice',
            'last_name' => 'Wonder',
            'email' => 'alice@example.com',
        ]);

        $this->assertDatabaseHas('students', [
            'first_name' => 'Alice',
            'last_name' => 'Wonder',
            'email' => 'alice@example.com',
        ]);
    }

    public function test_import_with_all_fields(): void
    {
        $import = new StudentsImport;

        $student = $import->model([
            'student_id' => 'STU001',
            'first_name' => 'Bob',
            'last_name' => 'Smith',
            'email' => 'bob@example.com',
            'gender' => 'Male',
            'program' => 'Computer Science',
            'year_of_study' => 2,
        ]);

        $this->assertInstanceOf(Student::class, $student);
        $this->assertEquals('STU001', $student->student_id_number);
        $this->assertEquals('Male', $student->gender);
        $this->assertEquals('Computer Science', $student->program);
        $this->assertEquals(2, $student->year_of_study);
    }

    public function test_import_skips_row_with_missing_required_fields(): void
    {
        $import = new StudentsImport;

        $student = $import->model([
            'email' => 'incomplete@example.com',
        ]);

        $this->assertNull($student);
    }

    public function test_import_does_not_duplicate_existing_student(): void
    {
        Student::factory()->create(['email' => 'existing@example.com']);

        $import = new StudentsImport;

        $student = $import->model([
            'first_name' => 'New',
            'last_name' => 'Name',
            'email' => 'existing@example.com',
        ]);

        $this->assertInstanceOf(Student::class, $student);
        $this->assertCount(1, Student::where('email', 'existing@example.com')->get());
    }
}
