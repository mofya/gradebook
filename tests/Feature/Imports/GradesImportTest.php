<?php

namespace Tests\Feature\Imports;

use App\Imports\GradesImport;
use App\Models\Assessment;
use App\Models\AssessmentGroup;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\GradeResult;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Year;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class GradesImportTest extends TestCase
{
    use RefreshDatabase;

    private CourseOffering $offering;

    private Student $student;

    private Assessment $assessment;

    private Enrollment $enrollment;

    protected function setUp(): void
    {
        parent::setUp();

        $year = Year::factory()->create();
        $semester = Semester::factory()->create(['year_id' => $year->id]);
        $course = Course::factory()->create(['year_id' => $year->id]);

        $this->offering = CourseOffering::factory()->create([
            'course_id' => $course->id,
            'semester_id' => $semester->id,
        ]);

        $group = AssessmentGroup::factory()->create([
            'course_offering_id' => $this->offering->id,
            'type' => 'ca',
        ]);

        $this->assessment = Assessment::factory()->create([
            'assessment_group_id' => $group->id,
            'course_id' => $course->id,
            'name' => 'Assignment 1',
            'max_raw_score' => 100,
        ]);

        $this->student = Student::factory()->create(['student_id_number' => 'SN123456789']);

        $this->enrollment = Enrollment::factory()->create([
            'student_id' => $this->student->id,
            'course_offering_id' => $this->offering->id,
        ]);
    }

    private function createCsvFile(array $rows): string
    {
        $path = storage_path('app/test-grades-import.csv');
        $handle = fopen($path, 'w');
        fputcsv($handle, ['student_id', 'assessment_name', 'raw_score']);
        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }
        fclose($handle);

        return $path;
    }

    public function test_imports_valid_grade_rows(): void
    {
        $path = $this->createCsvFile([
            ['SN123456789', 'Assignment 1', '85.5'],
        ]);

        $import = new GradesImport($this->offering);
        Excel::import($import, $path);

        $this->assertEquals(1, $import->getImportedCount());
        $this->assertEquals(0, $import->getSkippedCount());

        $result = GradeResult::where('enrollment_id', $this->enrollment->id)
            ->where('assessment_id', $this->assessment->id)
            ->first();

        $this->assertNotNull($result);
        $this->assertEquals(85.50, (float) $result->raw_score);
        $this->assertEquals('csv_import', $result->source);
    }

    public function test_skips_rows_with_unknown_student(): void
    {
        $path = $this->createCsvFile([
            ['UNKNOWN_ID', 'Assignment 1', '75'],
        ]);

        $import = new GradesImport($this->offering);
        Excel::import($import, $path);

        $this->assertEquals(0, $import->getImportedCount());
        $this->assertEquals(1, $import->getSkippedCount());
    }

    public function test_skips_rows_with_unknown_assessment(): void
    {
        $path = $this->createCsvFile([
            ['SN123456789', 'Nonexistent Assessment', '90'],
        ]);

        $import = new GradesImport($this->offering);
        Excel::import($import, $path);

        $this->assertEquals(0, $import->getImportedCount());
        $this->assertEquals(1, $import->getSkippedCount());
    }

    public function test_skips_rows_with_missing_fields(): void
    {
        $path = $this->createCsvFile([
            ['SN123456789', '', '90'],
            ['', 'Assignment 1', '80'],
        ]);

        $import = new GradesImport($this->offering);
        Excel::import($import, $path);

        $this->assertEquals(0, $import->getImportedCount());
        $this->assertEquals(2, $import->getSkippedCount());
    }

    public function test_updates_existing_grade_on_reimport(): void
    {
        GradeResult::factory()->create([
            'enrollment_id' => $this->enrollment->id,
            'assessment_id' => $this->assessment->id,
            'raw_score' => 50.00,
        ]);

        $path = $this->createCsvFile([
            ['SN123456789', 'Assignment 1', '95'],
        ]);

        $import = new GradesImport($this->offering);
        Excel::import($import, $path);

        $this->assertEquals(1, $import->getImportedCount());

        $result = GradeResult::where('enrollment_id', $this->enrollment->id)
            ->where('assessment_id', $this->assessment->id)
            ->first();

        $this->assertEquals(95.00, (float) $result->raw_score);
    }

    public function test_imports_multiple_rows(): void
    {
        $student2 = Student::factory()->create(['student_id_number' => 'SN987654321']);
        Enrollment::factory()->create([
            'student_id' => $student2->id,
            'course_offering_id' => $this->offering->id,
        ]);

        $path = $this->createCsvFile([
            ['SN123456789', 'Assignment 1', '80'],
            ['SN987654321', 'Assignment 1', '70'],
        ]);

        $import = new GradesImport($this->offering);
        Excel::import($import, $path);

        $this->assertEquals(2, $import->getImportedCount());
        $this->assertEquals(0, $import->getSkippedCount());
    }
}
