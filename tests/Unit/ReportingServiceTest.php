<?php

namespace Tests\Unit;

use App\Services\GradingService;
use App\Services\ReportingService;
use PHPUnit\Framework\TestCase;

class ReportingServiceTest extends TestCase
{
    private ReportingService $reportingService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->reportingService = new ReportingService(new GradingService);
    }

    public function test_it_calculates_grade_distribution(): void
    {
        $enrollments = collect([
            (object) ['final_grade' => 'A+', 'final_total' => 95],
            (object) ['final_grade' => 'A', 'final_total' => 85],
            (object) ['final_grade' => 'B', 'final_total' => 65],
            (object) ['final_grade' => 'D', 'final_total' => 30],
            (object) ['final_grade' => 'D+', 'final_total' => 37],
        ]);

        $distribution = $this->reportingService->getGradeDistribution($enrollments);

        $this->assertSame(1, $distribution['A+']);
        $this->assertSame(1, $distribution['A']);
        $this->assertSame(1, $distribution['B']);
        $this->assertSame(1, $distribution['D+']);
        $this->assertSame(1, $distribution['D']);
        $this->assertSame(0, $distribution['C']);
    }

    public function test_it_handles_empty_grade_distribution(): void
    {
        $distribution = $this->reportingService->getGradeDistribution(collect());

        $this->assertSame(0, $distribution['A+']);
        $this->assertSame(0, $distribution['D']);
    }

    public function test_it_counts_ne_grades_in_distribution(): void
    {
        $enrollments = collect([
            (object) ['final_grade' => 'NE', 'final_total' => null],
            (object) ['final_grade' => 'A', 'final_total' => 85],
        ]);

        $distribution = $this->reportingService->getGradeDistribution($enrollments);

        $this->assertSame(1, $distribution['NE']);
        $this->assertSame(1, $distribution['A']);
    }
}
