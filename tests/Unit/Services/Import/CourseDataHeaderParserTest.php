<?php

namespace Tests\Unit\Services\Import;

use App\Services\Import\CourseDataHeaderParser;
use Tests\TestCase;

class CourseDataHeaderParserTest extends TestCase
{
    private CourseDataHeaderParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new CourseDataHeaderParser;
    }

    public function test_detects_student_id_role(): void
    {
        $this->assertEquals('student_id', $this->parser->detectRole('student id', 'Student ID'));
        $this->assertEquals('student_id', $this->parser->detectRole('comp no', 'Comp No'));
        $this->assertEquals('student_id', $this->parser->detectRole('matric', 'Matric'));
    }

    public function test_detects_name_roles(): void
    {
        $this->assertEquals('first_name', $this->parser->detectRole('first name', 'First Name'));
        $this->assertEquals('last_name', $this->parser->detectRole('surname', 'Surname'));
        $this->assertEquals('full_name', $this->parser->detectRole('name', 'Name'));
    }

    public function test_detects_skip_for_computed_columns(): void
    {
        $this->assertEquals('skip', $this->parser->detectRole('ca/100', 'CA/100'));
        $this->assertEquals('skip', $this->parser->detectRole('final grade', 'Final Grade'));
        $this->assertEquals('skip', $this->parser->detectRole('total', 'Total'));
        $this->assertEquals('skip', $this->parser->detectRole('no', 'No'));
    }

    public function test_detects_exam_score(): void
    {
        $this->assertEquals('exam_score', $this->parser->detectRole('exam', 'Exam'));
        $this->assertEquals('exam_score', $this->parser->detectRole('exam/60', 'Exam/60'));
    }

    public function test_parses_assessment_header_with_parentheses(): void
    {
        $result = $this->parser->parseAssessmentHeader('Quiz 1 (30)');
        $this->assertEquals('Quiz 1', $result['name']);
        $this->assertEquals(30.0, $result['max_score']);
    }

    public function test_parses_assessment_header_with_slash(): void
    {
        $result = $this->parser->parseAssessmentHeader('Assignment 1/20');
        $this->assertEquals('Assignment 1', $result['name']);
        $this->assertEquals(20.0, $result['max_score']);
    }

    public function test_parses_exam_denominator(): void
    {
        $this->assertEquals(60.0, $this->parser->parseExamDenominator('Exam/60'));
        $this->assertEquals(100.0, $this->parser->parseExamDenominator('Exam (100)'));
        $this->assertNull($this->parser->parseExamDenominator('Exam'));
    }

    public function test_auto_selects_data_sheet(): void
    {
        $result = $this->parser->autoSelectSheet(['Summary', 'Data', 'Chart']);
        $this->assertEquals('Data', $result);
    }

    public function test_auto_selects_course_code_sheet(): void
    {
        $result = $this->parser->autoSelectSheet(['Sheet1', 'CSC101', 'Report'], 'CSC101');
        $this->assertEquals('CSC101', $result);
    }

    public function test_flags_report_sheets(): void
    {
        $flags = $this->parser->flagReportSheets(['Data', 'Grade Summary', 'Chart']);
        $this->assertFalse($flags['Data']);
        $this->assertTrue($flags['Grade Summary']);
        $this->assertTrue($flags['Chart']);
    }

    public function test_full_header_parsing(): void
    {
        $headers = ['No', 'Student ID', 'First Name', 'Last Name', 'Quiz 1 (30)', 'Exam/60'];
        $mappings = $this->parser->parseHeaders($headers);

        $this->assertEquals('skip', $mappings[0]['confirmed_role']);
        $this->assertEquals('student_id', $mappings[1]['confirmed_role']);
        $this->assertEquals('first_name', $mappings[2]['confirmed_role']);
        $this->assertEquals('last_name', $mappings[3]['confirmed_role']);
        $this->assertEquals('ca_assessment', $mappings[4]['confirmed_role']);
        $this->assertEquals('Quiz 1', $mappings[4]['assessment_name']);
        $this->assertEquals(30.0, $mappings[4]['max_score']);
        $this->assertEquals('exam_score', $mappings[5]['confirmed_role']);
        $this->assertEquals(60.0, $mappings[5]['max_score']);
    }
}
