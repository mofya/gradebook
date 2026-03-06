<?php

namespace Tests\Feature\Services;

use App\Models\Assessment;
use App\Models\AssessmentGroup;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\GradeResult;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Year;
use App\Services\CourseDataImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourseDataImportServiceTest extends TestCase
{
    use RefreshDatabase;

    private CourseDataImportService $service;

    private CourseOffering $offering;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(CourseDataImportService::class);

        $year = Year::factory()->create();
        $semester = Semester::factory()->create(['year_id' => $year->id]);
        $course = Course::factory()->create(['year_id' => $year->id]);

        $this->offering = CourseOffering::factory()->create([
            'course_id' => $course->id,
            'semester_id' => $semester->id,
            'ca_weight' => 50,
            'exam_weight' => 50,
        ]);
    }

    public function test_parses_standard_unza_headers(): void
    {
        $headers = ['No', 'Student ID', 'First Name', 'Last Name', 'Gender', 'Quiz 1 (30)', 'Test 1 (50)', 'Exam/60', 'CA/100', 'Final Mark', 'Grade'];

        $mappings = $this->service->parseHeaders($headers);

        $this->assertEquals('skip', $mappings[0]['confirmed_role']);
        $this->assertEquals('student_id', $mappings[1]['confirmed_role']);
        $this->assertEquals('first_name', $mappings[2]['confirmed_role']);
        $this->assertEquals('last_name', $mappings[3]['confirmed_role']);
        $this->assertEquals('gender', $mappings[4]['confirmed_role']);
        $this->assertEquals('ca_assessment', $mappings[5]['confirmed_role']);
        $this->assertEquals('ca_assessment', $mappings[6]['confirmed_role']);
        $this->assertEquals('exam_score', $mappings[7]['confirmed_role']);
        $this->assertEquals('skip', $mappings[8]['confirmed_role']);
        $this->assertEquals('skip', $mappings[9]['confirmed_role']);
        $this->assertEquals('skip', $mappings[10]['confirmed_role']);
    }

    public function test_detects_assessment_columns_with_max_scores(): void
    {
        $headers = ['Student ID', 'Quiz 1 (30)', 'Assignment 1 (20)', 'Test 1 (50)'];

        $mappings = $this->service->parseHeaders($headers);

        $this->assertEquals('Quiz 1', $mappings[1]['assessment_name']);
        $this->assertEquals(30.0, $mappings[1]['max_score']);

        $this->assertEquals('Assignment 1', $mappings[2]['assessment_name']);
        $this->assertEquals(20.0, $mappings[2]['max_score']);

        $this->assertEquals('Test 1', $mappings[3]['assessment_name']);
        $this->assertEquals(50.0, $mappings[3]['max_score']);
    }

    public function test_detects_exam_column_with_denominator(): void
    {
        $headers = ['Student ID', 'Exam/60'];

        $mappings = $this->service->parseHeaders($headers);

        $this->assertEquals('exam_score', $mappings[1]['confirmed_role']);
        $this->assertEquals(60.0, $mappings[1]['max_score']);
    }

    public function test_detects_computed_columns_as_skip(): void
    {
        $headers = ['CA/100', 'CA Grade', 'Final Mark', 'Grade', 'GP', 'Total', 'Remark'];

        $mappings = $this->service->parseHeaders($headers);

        foreach ($mappings as $mapping) {
            $this->assertEquals('skip', $mapping['confirmed_role'], "Expected '{$mapping['header']}' to be skip.");
        }
    }

    public function test_import_creates_assessment_group_and_assessments(): void
    {
        $mappings = $this->service->parseHeaders(['Student ID', 'Quiz 1 (30)', 'Test 1 (70)']);
        $rows = [
            ['SN000000001', '25', '60'],
        ];

        $this->service->import($this->offering, $rows, $mappings);

        $this->assertDatabaseHas('assessment_groups', [
            'course_offering_id' => $this->offering->id,
            'type' => 'ca',
            'name' => 'Continuous Assessment',
        ]);

        $group = AssessmentGroup::where('course_offering_id', $this->offering->id)->where('type', 'ca')->first();

        $this->assertDatabaseHas('assessments', [
            'assessment_group_id' => $group->id,
            'name' => 'Quiz 1',
            'max_raw_score' => 30,
        ]);

        $this->assertDatabaseHas('assessments', [
            'assessment_group_id' => $group->id,
            'name' => 'Test 1',
            'max_raw_score' => 70,
        ]);
    }

    public function test_normalized_to_values_sum_to_100(): void
    {
        $mappings = $this->service->parseHeaders(['Student ID', 'Quiz 1 (30)', 'Assignment 1 (20)', 'Test 1 (50)']);
        $rows = [
            ['SN000000001', '25', '18', '40'],
        ];

        $this->service->import($this->offering, $rows, $mappings);

        $group = AssessmentGroup::where('course_offering_id', $this->offering->id)->where('type', 'ca')->first();
        $assessments = Assessment::where('assessment_group_id', $group->id)->get();

        $sumNormalized = $assessments->sum('normalized_to');

        $this->assertEquals(100.0, round($sumNormalized, 2));
    }

    public function test_import_creates_students_and_enrollments(): void
    {
        $mappings = $this->service->parseHeaders(['Student ID', 'First Name', 'Last Name', 'Quiz 1 (10)']);
        $rows = [
            ['SN100000001', 'Alice', 'Smith', '8'],
            ['SN100000002', 'Bob', 'Jones', '7'],
        ];

        $results = $this->service->import($this->offering, $rows, $mappings);

        $this->assertEquals(2, $results['students_created']);
        $this->assertEquals(2, $results['enrollments_created']);

        $this->assertDatabaseHas('students', ['student_id_number' => 'SN100000001', 'first_name' => 'Alice']);
        $this->assertDatabaseHas('students', ['student_id_number' => 'SN100000002', 'first_name' => 'Bob']);
        $this->assertDatabaseCount('enrollments', 2);
    }

    public function test_import_creates_grade_results(): void
    {
        $mappings = $this->service->parseHeaders(['Student ID', 'Quiz 1 (30)', 'Test 1 (70)']);
        $rows = [
            ['SN200000001', '25', '60'],
        ];

        $results = $this->service->import($this->offering, $rows, $mappings);

        $this->assertEquals(2, $results['grades_imported']);
        $this->assertDatabaseCount('grade_results', 2);

        $student = Student::where('student_id_number', 'SN200000001')->first();
        $enrollment = Enrollment::where('student_id', $student->id)->first();

        $gradeResults = GradeResult::where('enrollment_id', $enrollment->id)->get();
        $this->assertCount(2, $gradeResults);

        $this->assertTrue($gradeResults->contains(fn ($gr) => (float) $gr->raw_score === 25.0));
        $this->assertTrue($gradeResults->contains(fn ($gr) => (float) $gr->raw_score === 60.0));
    }

    public function test_import_sets_exam_score_on_enrollment(): void
    {
        $mappings = $this->service->parseHeaders(['Student ID', 'Quiz 1 (10)', 'Exam']);
        $rows = [
            ['SN300000001', '8', '75'],
        ];

        $results = $this->service->import($this->offering, $rows, $mappings);

        $this->assertEquals(1, $results['exam_scores_set']);

        $student = Student::where('student_id_number', 'SN300000001')->first();
        $enrollment = Enrollment::where('student_id', $student->id)->first();

        $this->assertEquals(75.0, (float) $enrollment->exam_score);
    }

    public function test_import_converts_exam_score_when_not_out_of_100(): void
    {
        $mappings = $this->service->parseHeaders(['Student ID', 'Quiz 1 (10)', 'Exam/60']);
        $rows = [
            ['SN400000001', '8', '45'],
        ];

        $results = $this->service->import($this->offering, $rows, $mappings);

        $student = Student::where('student_id_number', 'SN400000001')->first();
        $enrollment = Enrollment::where('student_id', $student->id)->first();

        // 45/60 * 100 = 75
        $this->assertEquals(75.0, (float) $enrollment->exam_score);
    }

    public function test_import_resolves_all_grades(): void
    {
        $mappings = $this->service->parseHeaders(['Student ID', 'Quiz 1 (100)', 'Exam']);
        $rows = [
            ['SN500000001', '80', '70'],
        ];

        $results = $this->service->import($this->offering, $rows, $mappings);

        $this->assertGreaterThan(0, $results['grades_resolved']);

        $student = Student::where('student_id_number', 'SN500000001')->first();
        $enrollment = Enrollment::where('student_id', $student->id)->first();

        $this->assertNotNull($enrollment->final_total);
        $this->assertNotNull($enrollment->final_grade);
    }

    public function test_import_skips_rows_with_missing_student_id(): void
    {
        $mappings = $this->service->parseHeaders(['Student ID', 'Quiz 1 (10)']);
        $rows = [
            ['SN600000001', '8'],
            ['', '5'],
            [null, '3'],
        ];

        $results = $this->service->import($this->offering, $rows, $mappings);

        $this->assertEquals(1, $results['students_created']);
        $this->assertCount(2, $results['errors']);
    }

    public function test_import_handles_existing_students_without_duplicates(): void
    {
        $student = Student::factory()->create(['student_id_number' => 'SN700000001']);

        $mappings = $this->service->parseHeaders(['Student ID', 'Quiz 1 (10)']);
        $rows = [
            ['SN700000001', '9'],
        ];

        $results = $this->service->import($this->offering, $rows, $mappings);

        $this->assertEquals(1, $results['students_found']);
        $this->assertEquals(0, $results['students_created']);
        $this->assertDatabaseCount('students', 1);
    }

    public function test_import_uses_bulk_import_source(): void
    {
        $mappings = $this->service->parseHeaders(['Student ID', 'Quiz 1 (10)']);
        $rows = [
            ['SN800000001', '7'],
        ];

        $this->service->import($this->offering, $rows, $mappings);

        $this->assertDatabaseHas('grade_results', ['source' => 'bulk_import']);
        $this->assertDatabaseHas('enrollments', ['source' => 'bulk_import']);
    }

    public function test_validate_column_mappings_requires_student_id(): void
    {
        $mappings = $this->service->parseHeaders(['First Name', 'Last Name', 'Quiz 1 (10)']);

        $result = $this->service->validateColumnMappings($mappings);

        $this->assertFalse($result['valid']);
        $this->assertTrue(
            collect($result['errors'])->contains(fn ($e) => str_contains($e, 'Student ID'))
        );
    }

    public function test_non_numeric_score_adds_error_and_skips(): void
    {
        $mappings = $this->service->parseHeaders(['Student ID', 'Quiz 1 (10)']);
        $rows = [
            ['SN900000001', 'abc'],
        ];

        $results = $this->service->import($this->offering, $rows, $mappings);

        $this->assertEquals(0, $results['grades_imported']);
        $this->assertTrue(
            collect($results['errors'])->contains(fn ($e) => str_contains($e, 'Non-numeric'))
        );
    }

    public function test_short_student_id_adds_warning(): void
    {
        $mappings = $this->service->parseHeaders(['Student ID', 'Quiz 1 (10)']);
        $rows = [
            ['AB', '8'],
        ];

        $results = $this->service->import($this->offering, $rows, $mappings);

        $this->assertTrue(
            collect($results['errors'])->contains(fn ($e) => str_contains($e, 'unusually short'))
        );
    }

    public function test_validate_column_mappings_requires_identity_fields(): void
    {
        $mappings = $this->service->parseHeaders(['Student ID', 'Quiz 1 (10)', 'Exam/60']);

        $result = $this->service->validateColumnMappings($mappings);

        $this->assertFalse($result['valid']);
        $this->assertTrue(collect($result['errors'])->contains(fn ($e) => str_contains($e, 'First Name')));
        $this->assertTrue(collect($result['errors'])->contains(fn ($e) => str_contains($e, 'Last Name')));
        $this->assertTrue(collect($result['errors'])->contains(fn ($e) => str_contains($e, 'Email')));
    }

    public function test_preflight_blocks_missing_and_invalid_identity_data(): void
    {
        $mappings = $this->service->parseHeaders(['Student ID', 'First Name', 'Last Name', 'Email', 'Quiz 1 (10)', 'Exam/60']);
        $rows = [
            ['SN910000001', 'Jane', 'Doe', 'jane@example.com', '9', '55'],
            ['SN910000002', 'John', '', 'not-an-email', '8', '50'],
            ['SN910000002', 'John', 'Smith', 'john@example.com', 'abc', '40'],
        ];

        $result = $this->service->preflight($rows, $mappings);

        $this->assertFalse($result['valid']);
        $this->assertTrue(collect($result['errors'])->contains(fn ($e) => str_contains($e, 'Missing Last Name')));
        $this->assertTrue(collect($result['errors'])->contains(fn ($e) => str_contains($e, 'Invalid email')));
        $this->assertTrue(collect($result['errors'])->contains(fn ($e) => str_contains($e, 'Duplicate Student ID')));
        $this->assertTrue(collect($result['errors'])->contains(fn ($e) => str_contains($e, 'Non-numeric CA score')));
    }
}
