<?php

namespace Tests\Unit\Services\Import;

use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Semester;
use App\Models\Year;
use App\Services\Import\CourseDataHeaderParser;
use App\Services\Import\CourseDataProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourseDataProcessorTest extends TestCase
{
    use RefreshDatabase;

    private CourseDataProcessor $processor;

    private CourseDataHeaderParser $parser;

    private CourseOffering $offering;

    protected function setUp(): void
    {
        parent::setUp();

        $this->processor = app(CourseDataProcessor::class);
        $this->parser = new CourseDataHeaderParser;

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

    public function test_import_creates_students_and_grades(): void
    {
        $mappings = $this->parser->parseHeaders(['Student ID', 'Quiz 1 (30)', 'Test 1 (70)']);
        $rows = [
            ['SNPROC001', '25', '60'],
        ];

        $results = $this->processor->import($this->offering, $rows, $mappings);

        $this->assertEquals(1, $results['students_created']);
        $this->assertEquals(2, $results['grades_imported']);
    }

    public function test_filter_data_rows_removes_summary_rows(): void
    {
        $mappings = $this->parser->parseHeaders(['Student ID', 'Quiz 1 (10)']);
        $rows = [
            ['SN001', '8'],
            ['SN002', '7'],
            ['Total', '15'],
            ['Average', '7.5'],
        ];

        $result = $this->processor->filterDataRows($rows, $mappings);

        $this->assertCount(2, $result['rows']);
        $this->assertEquals(2, $result['skipped']);
    }

    public function test_filter_data_rows_stops_at_consecutive_blanks(): void
    {
        $mappings = $this->parser->parseHeaders(['Student ID', 'Quiz 1 (10)']);
        $rows = [
            ['SN001', '8'],
            ['', ''],
            ['', ''],
            ['SN003', '9'],
        ];

        $result = $this->processor->filterDataRows($rows, $mappings);

        $this->assertCount(1, $result['rows']);
    }
}
