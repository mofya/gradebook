<?php

namespace Tests\Unit\Services\Import;

use App\Services\Import\CourseDataHeaderParser;
use App\Services\Import\CourseDataValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourseDataValidatorTest extends TestCase
{
    use RefreshDatabase;

    private CourseDataValidator $validator;

    private CourseDataHeaderParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new CourseDataValidator;
        $this->parser = new CourseDataHeaderParser;
    }

    public function test_validates_missing_student_id(): void
    {
        $mappings = $this->parser->parseHeaders(['First Name', 'Last Name', 'Quiz 1 (10)']);
        $result = $this->validator->validateColumnMappings($mappings);

        $this->assertFalse($result['valid']);
        $this->assertTrue(collect($result['errors'])->contains(fn ($e) => str_contains($e, 'Student ID')));
    }

    public function test_validates_missing_name_columns(): void
    {
        $mappings = $this->parser->parseHeaders(['Student ID', 'Email', 'Quiz 1 (10)']);
        $result = $this->validator->validateColumnMappings($mappings);

        $this->assertFalse($result['valid']);
    }

    public function test_valid_column_mappings_pass(): void
    {
        $mappings = $this->parser->parseHeaders(['Student ID', 'First Name', 'Last Name', 'Email', 'Quiz 1 (10)']);
        $result = $this->validator->validateColumnMappings($mappings);

        $this->assertTrue($result['valid']);
    }

    public function test_preflight_detects_duplicate_student_ids(): void
    {
        $mappings = $this->parser->parseHeaders(['Student ID', 'First Name', 'Last Name', 'Email', 'Quiz 1 (10)']);
        $rows = [
            ['SN001', 'John', 'Doe', 'john@test.com', '8'],
            ['SN001', 'Jane', 'Doe', 'jane@test.com', '7'],
        ];

        $result = $this->validator->preflight($rows, $mappings);

        $this->assertFalse($result['valid']);
        $this->assertTrue(collect($result['errors'])->contains(fn ($e) => str_contains($e, 'Duplicate Student ID')));
    }

    public function test_preflight_detects_invalid_emails(): void
    {
        $mappings = $this->parser->parseHeaders(['Student ID', 'First Name', 'Last Name', 'Email', 'Quiz 1 (10)']);
        $rows = [
            ['SN001', 'John', 'Doe', 'not-an-email', '8'],
        ];

        $result = $this->validator->preflight($rows, $mappings);

        $this->assertFalse($result['valid']);
        $this->assertTrue(collect($result['errors'])->contains(fn ($e) => str_contains($e, 'Invalid email')));
    }

    public function test_infer_max_scores_from_data(): void
    {
        $mappings = [
            ['index' => 0, 'header' => 'Student ID', 'detected_role' => 'student_id', 'confirmed_role' => 'student_id', 'assessment_name' => null, 'max_score' => null],
            ['index' => 1, 'header' => 'Quiz', 'detected_role' => 'ca_assessment', 'confirmed_role' => 'ca_assessment', 'assessment_name' => 'Quiz', 'max_score' => null],
        ];

        $rows = [
            ['SN001', '18'],
            ['SN002', '15'],
        ];

        $result = $this->validator->inferMaxScores($rows, $mappings);

        $this->assertEquals(20.0, $result[1]['max_score']);
    }
}
