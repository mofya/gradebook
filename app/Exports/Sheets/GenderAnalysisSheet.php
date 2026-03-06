<?php

namespace App\Exports\Sheets;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class GenderAnalysisSheet implements FromArray, WithColumnWidths, WithStyles, WithTitle
{
    protected const GRADES = ['NE', 'D', 'D+', 'C', 'C+', 'B', 'B+', 'A', 'A+'];

    protected const PASS_GRADES = ['C', 'C+', 'B', 'B+', 'A', 'A+'];

    protected const FAIL_GRADES = ['D', 'D+'];

    /** @var array<int, array<int, mixed>> */
    protected array $rows = [];

    /** @var array<string, array<string, int>> */
    protected array $genderGradeCounts = [];

    public function __construct(
        protected Collection $enrollments,
    ) {
        $this->computeCrossTabs();
        $this->buildRows();
    }

    public function title(): string
    {
        return 'Gender Analysis';
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
            'A' => 16,
            'B' => 10,
            'C' => 10,
            'D' => 10,
            'E' => 10,
            'F' => 10,
            'G' => 10,
            'H' => 10,
            'I' => 10,
            'J' => 10,
            'K' => 10,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true, 'size' => 12]],
            2 => ['font' => ['bold' => true]],
        ];
    }

    protected function computeCrossTabs(): void
    {
        $this->genderGradeCounts = [];

        foreach ($this->enrollments as $enrollment) {
            $gender = $enrollment->student->gender ?? 'Unknown';
            $grade = $enrollment->final_grade ?? 'NE';

            if (! isset($this->genderGradeCounts[$gender])) {
                $this->genderGradeCounts[$gender] = array_fill_keys(self::GRADES, 0);
            }

            if (isset($this->genderGradeCounts[$gender][$grade])) {
                $this->genderGradeCounts[$gender][$grade]++;
            }
        }
    }

    /**
     * @return array<string, array<string, int>>
     */
    public function getGenderGradeCounts(): array
    {
        return $this->genderGradeCounts;
    }

    protected function buildRows(): void
    {
        $genders = array_keys($this->genderGradeCounts);
        sort($genders);

        // Table 1: GRADE DISTRIBUTION BY GENDER
        $this->rows[] = array_merge(['GRADE DISTRIBUTION BY GENDER'], self::GRADES, ['Total']);

        // Header row
        $this->rows[] = array_merge([''], self::GRADES, ['Total']);

        foreach ($genders as $gender) {
            $row = [$gender];
            $total = 0;
            foreach (self::GRADES as $grade) {
                $count = $this->genderGradeCounts[$gender][$grade] ?? 0;
                $row[] = $count;
                $total += $count;
            }
            $row[] = $total;
            $this->rows[] = $row;
        }

        // Totals row
        $totalsRow = ['Total'];
        $grandTotal = 0;
        foreach (self::GRADES as $grade) {
            $sum = 0;
            foreach ($genders as $gender) {
                $sum += $this->genderGradeCounts[$gender][$grade] ?? 0;
            }
            $totalsRow[] = $sum;
            $grandTotal += $sum;
        }
        $totalsRow[] = $grandTotal;
        $this->rows[] = $totalsRow;

        // Blank row
        $this->rows[] = [];

        // Table 2: PERCENTAGE BY GENDER (each row sums to 100%)
        $this->rows[] = array_merge(['PERCENTAGE BY GENDER'], self::GRADES, ['Total']);
        $this->rows[] = array_merge([''], self::GRADES, ['Total']);

        foreach ($genders as $gender) {
            $row = [$gender];
            $total = 0;
            foreach (self::GRADES as $grade) {
                $total += $this->genderGradeCounts[$gender][$grade] ?? 0;
            }

            foreach (self::GRADES as $grade) {
                $count = $this->genderGradeCounts[$gender][$grade] ?? 0;
                $pct = $total > 0 ? round(($count / $total) * 100, 1) : 0;
                $row[] = $pct.'%';
            }
            $row[] = '100%';
            $this->rows[] = $row;
        }

        // Blank row
        $this->rows[] = [];

        // Table 3: PASS / FAIL / NE BY GENDER
        $this->rows[] = ['PASS / FAIL / NE BY GENDER', 'Pass', 'Fail', 'NE', 'Total'];
        $this->rows[] = ['', 'Pass', 'Fail', 'NE', 'Total'];

        $totalPass = 0;
        $totalFail = 0;
        $totalNe = 0;

        foreach ($genders as $gender) {
            $pass = 0;
            foreach (self::PASS_GRADES as $g) {
                $pass += $this->genderGradeCounts[$gender][$g] ?? 0;
            }

            $fail = 0;
            foreach (self::FAIL_GRADES as $g) {
                $fail += $this->genderGradeCounts[$gender][$g] ?? 0;
            }

            $ne = $this->genderGradeCounts[$gender]['NE'] ?? 0;
            $genderTotal = $pass + $fail + $ne;

            $this->rows[] = [$gender, $pass, $fail, $ne, $genderTotal];

            $totalPass += $pass;
            $totalFail += $fail;
            $totalNe += $ne;
        }

        $this->rows[] = ['Total', $totalPass, $totalFail, $totalNe, $totalPass + $totalFail + $totalNe];
    }
}
