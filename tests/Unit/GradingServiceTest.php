<?php

namespace Tests\Unit;

use App\Services\GradingService;
use PHPUnit\Framework\TestCase;

class GradingServiceTest extends TestCase
{
    private GradingService $gradingService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->gradingService = new GradingService;
    }

    public function test_it_returns_a_plus_for_marks_90_to_100(): void
    {
        $this->assertSame('A+', $this->gradingService->getLetterGrade(90));
        $this->assertSame('A+', $this->gradingService->getLetterGrade(100));
        $this->assertSame('A+', $this->gradingService->getLetterGrade(95));
    }

    public function test_it_returns_a_for_marks_80_to_89(): void
    {
        $this->assertSame('A', $this->gradingService->getLetterGrade(80));
        $this->assertSame('A', $this->gradingService->getLetterGrade(89));
        $this->assertSame('A', $this->gradingService->getLetterGrade(85));
    }

    public function test_it_returns_b_plus_for_marks_70_to_79(): void
    {
        $this->assertSame('B+', $this->gradingService->getLetterGrade(70));
        $this->assertSame('B+', $this->gradingService->getLetterGrade(79));
    }

    public function test_it_returns_b_for_marks_60_to_69(): void
    {
        $this->assertSame('B', $this->gradingService->getLetterGrade(60));
        $this->assertSame('B', $this->gradingService->getLetterGrade(69));
    }

    public function test_it_returns_c_plus_for_marks_50_to_59(): void
    {
        $this->assertSame('C+', $this->gradingService->getLetterGrade(50));
        $this->assertSame('C+', $this->gradingService->getLetterGrade(59));
    }

    public function test_it_returns_c_for_marks_40_to_49(): void
    {
        $this->assertSame('C', $this->gradingService->getLetterGrade(40));
        $this->assertSame('C', $this->gradingService->getLetterGrade(49));
    }

    public function test_it_returns_d_plus_for_marks_35_to_39(): void
    {
        $this->assertSame('D+', $this->gradingService->getLetterGrade(35));
        $this->assertSame('D+', $this->gradingService->getLetterGrade(39));
    }

    public function test_it_returns_d_for_marks_0_to_34(): void
    {
        $this->assertSame('D', $this->gradingService->getLetterGrade(0));
        $this->assertSame('D', $this->gradingService->getLetterGrade(34));
        $this->assertSame('D', $this->gradingService->getLetterGrade(20));
    }

    public function test_it_validates_marks_within_range(): void
    {
        $this->assertTrue($this->gradingService->isValidMark(0));
        $this->assertTrue($this->gradingService->isValidMark(50));
        $this->assertTrue($this->gradingService->isValidMark(100));
    }

    public function test_it_rejects_marks_outside_range(): void
    {
        $this->assertFalse($this->gradingService->isValidMark(-1));
        $this->assertFalse($this->gradingService->isValidMark(101));
    }

    public function test_it_calculates_grade_points(): void
    {
        $this->assertSame(4.0, $this->gradingService->getGradePoints(90));  // A+
        $this->assertSame(4.0, $this->gradingService->getGradePoints(85));  // A
        $this->assertSame(3.0, $this->gradingService->getGradePoints(65));  // B
        $this->assertSame(2.0, $this->gradingService->getGradePoints(45));  // C
        $this->assertSame(1.0, $this->gradingService->getGradePoints(20));  // D
    }

    public function test_it_calculates_semester_gpa(): void
    {
        $results = [
            ['mark' => 85, 'credits' => 3], // A  => 4.0
            ['mark' => 65, 'credits' => 3], // B  => 3.0
            ['mark' => 45, 'credits' => 3], // C  => 2.0
        ];

        // (4.0*3 + 3.0*3 + 2.0*3) / 9 = 27/9 = 3.0
        $this->assertSame(3.0, $this->gradingService->calculateSemesterGpa($results));
    }

    public function test_it_returns_zero_gpa_for_empty_results(): void
    {
        $this->assertSame(0.0, $this->gradingService->calculateSemesterGpa([]));
    }
}
