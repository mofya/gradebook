<?php

namespace App\Exports\Sheets;

use App\Models\CourseOffering;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class GradeSummarySheet implements FromArray, WithColumnWidths, WithStyles, WithTitle
{
    /** @var array<int, array<int, mixed>> */
    protected array $rows = [];

    /**
     * @param  array<string, mixed>  $reportData
     */
    public function __construct(
        protected CourseOffering $courseOffering,
        protected array $reportData,
    ) {
        $this->buildRows();
    }

    public function title(): string
    {
        return 'Grade Summary';
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    public function array(): array
    {
        return $this->rows;
    }

    /**
     * @return array<string, float>
     */
    public function columnWidths(): array
    {
        return [
            'A' => 18,
            'B' => 14,
            'C' => 14,
            'D' => 14,
            'E' => 14,
            'F' => 14,
            'G' => 14,
            'H' => 14,
            'I' => 14,
            'J' => 14,
            'K' => 14,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true, 'size' => 14]],
            2 => ['font' => ['bold' => false]],
            4 => ['font' => ['bold' => true, 'size' => 12]],
            5 => ['font' => ['bold' => true]],
            8 => ['font' => ['bold' => true, 'size' => 12]],
            9 => ['font' => ['bold' => true]],
            13 => ['font' => ['bold' => true, 'size' => 12]],
            14 => ['font' => ['bold' => true]],
        ];
    }

    protected function buildRows(): void
    {
        $co = $this->courseOffering;
        $stats = $this->reportData['stats'];
        $distribution = $this->reportData['distribution'];

        // Row 1-2: Course info
        $this->rows[] = [
            $co->course->code.' - '.$co->course->name,
        ];
        $this->rows[] = [
            'Semester: '.($co->semester->year->name ?? '').' '.$co->semester->name,
            null,
            'Lecturer: '.($co->lecturer->name ?? 'Unassigned'),
        ];

        // Row 3: blank
        $this->rows[] = [];

        // Row 4: CLASS STATISTICS header
        $this->rows[] = ['CLASS STATISTICS'];

        // Row 5: stat labels
        $this->rows[] = ['Enrolled', 'Graded', 'Average', 'Median', 'Std Dev', 'Highest', 'Lowest', 'Pass Rate'];

        // Row 6: stat values
        $this->rows[] = [
            $stats['total_enrolled'],
            $stats['graded'],
            $stats['average'],
            $stats['median'],
            $stats['std_deviation'],
            $stats['highest'],
            $stats['lowest'],
            $stats['pass_rate'].'%',
        ];

        // Row 7: blank
        $this->rows[] = [];

        // Row 8: GRADE DISTRIBUTION header
        $this->rows[] = ['GRADE DISTRIBUTION'];

        $grades = ['NE', 'D', 'D+', 'C', 'C+', 'B', 'B+', 'A', 'A+'];
        $totalGraded = max(array_sum($distribution), 1);

        // Row 9: grade labels
        $this->rows[] = array_merge(['Grade'], $grades);

        // Row 10: counts
        $counts = ['Count'];
        foreach ($grades as $grade) {
            $counts[] = $distribution[$grade] ?? 0;
        }
        $this->rows[] = $counts;

        // Row 11: percentages
        $percentages = ['%'];
        foreach ($grades as $grade) {
            $count = $distribution[$grade] ?? 0;
            $percentages[] = round(($count / $totalGraded) * 100, 1).'%';
        }
        $this->rows[] = $percentages;

        // Row 12: blank
        $this->rows[] = [];

        // Row 13: PASS / FAIL / NE SUMMARY header
        $this->rows[] = ['PASS / FAIL / NE SUMMARY'];

        $neCount = $distribution['NE'] ?? 0;
        $passGrades = ['C', 'C+', 'B', 'B+', 'A', 'A+'];
        $failGrades = ['D', 'D+'];

        $passCount = 0;
        foreach ($passGrades as $g) {
            $passCount += $distribution[$g] ?? 0;
        }

        $failCount = 0;
        foreach ($failGrades as $g) {
            $failCount += $distribution[$g] ?? 0;
        }

        $passFailTotal = $passCount + $failCount;

        // Row 14: labels
        $this->rows[] = ['', 'Pass', 'Fail', 'NE', 'Total'];

        // Row 15: counts
        $this->rows[] = ['Count', $passCount, $failCount, $neCount, $passCount + $failCount + $neCount];

        // Row 16: percentages (pass rate denominator EXCLUDES NE)
        $passRate = $passFailTotal > 0 ? round(($passCount / $passFailTotal) * 100, 1) : 0;
        $failRate = $passFailTotal > 0 ? round(($failCount / $passFailTotal) * 100, 1) : 0;
        $this->rows[] = [
            '%',
            $passRate.'%',
            $failRate.'%',
            'N/A',
            '',
        ];
    }
}
